<?php

namespace App\Pricing;

use App\Models\PriceObservation;
use App\Models\Receipt;
use App\Models\User;
use App\Support\Enums\PriceSource;
use App\Support\Enums\ReceiptStatus;
use Illuminate\Support\Collection;

/**
 * Everything on the user's dashboard, from the prices they can see (their own receipts plus
 * prices that belong to nobody).
 */
class Dashboard
{
    /** Price changes older than this aren't "recent". */
    private const RECENT_DAYS = 30;

    public function __construct(private User $user)
    {
    }

    public function toArray(): array
    {
        $changes = $this->recentChanges();

        return [
            'counts' => $this->counts(),
            'spent_this_month_cents' => (int) Receipt::whereBelongsTo($this->user)
                ->where('purchased_at', '>=', now()->startOfMonth())
                ->sum('total_cents'),
            'potential_savings_this_month_cents' => $this->potentialSavingsThisMonth(),
            'recent_changes' => $changes->take(8)->values(),
            'cheaper' => $changes->where('change_cents', '<', 0)->sortBy('change_cents')->take(5)->values(),
            'dearer' => $changes->where('change_cents', '>', 0)->sortByDesc('change_cents')->take(5)->values(),
            'savings_opportunities' => $this->savingsOpportunities(),
            'recent_receipts' => Receipt::whereBelongsTo($this->user)
                ->with('store')
                // Newest shop first; receipts still being read (no date yet) by upload time.
                ->orderByRaw('COALESCE(purchased_at, created_at) DESC')
                ->limit(5)
                ->get()
                ->map(fn (Receipt $receipt) => [
                    'id' => $receipt->id,
                    'store' => $receipt->store?->name,
                    'purchased_at' => $receipt->purchased_at?->format('Y-m-d H:i'),
                    'total_cents' => $receipt->total_cents,
                    'status' => $receipt->status->value,
                    'created_at' => $receipt->created_at,
                ]),
        ];
    }

    private function counts(): array
    {
        $prices = PriceObservation::visibleTo($this->user);

        return [
            'receipts' => Receipt::whereBelongsTo($this->user)->count(),
            'needs_review' => Receipt::whereBelongsTo($this->user)->whereIn('status', [ReceiptStatus::NeedsReview, ReceiptStatus::Failed])->count(),
            'products' => (clone $prices)->distinct()->count('product_id'),
            'stores' => (clone $prices)->distinct()->count('store_id'),
        ];
    }

    /**
     * Products whose latest price at a store differs from the one before it, newest first.
     */
    private function recentChanges(): Collection
    {
        $ranked = PriceObservation::visibleTo($this->user)
            ->select('price_observations.*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY product_id, store_id ORDER BY observed_on DESC, id DESC) AS recency');

        return PriceObservation::query()
            ->fromSub($ranked, 'price_observations')
            ->where('recency', '<=', 2)
            ->with('product.brand', 'store')
            ->get()
            ->groupBy(fn (PriceObservation $price) => "{$price->product_id}-{$price->store_id}")
            ->map(function (Collection $pair) {
                [$latest, $previous] = [$pair->firstWhere('recency', 1), $pair->firstWhere('recency', 2)];

                if (!$previous || $latest->price_cents === $previous->price_cents || $latest->observed_on->lt(now()->subDays(self::RECENT_DAYS))) {
                    return null;
                }

                return [
                    'product' => $this->product($latest),
                    'store' => ['id' => $latest->store->id, 'name' => $latest->store->name],
                    'unit' => $latest->unit->value,
                    'from_cents' => $previous->price_cents,
                    'to_cents' => $latest->price_cents,
                    'change_cents' => $latest->price_cents - $previous->price_cents,
                    'observed_on' => $latest->observed_on->toDateString(),
                ];
            })
            ->filter()
            ->sortByDesc('observed_on')
            ->values();
    }

    /**
     * Products where what the user last paid is well above the cheapest current price elsewhere.
     */
    private function savingsOpportunities(): Collection
    {
        $lastPaid = $this->lastPaidPerProduct(now()->subDays(90));
        $cheapest = $this->cheapestCurrentPerProduct($lastPaid->keys()->all());

        return $lastPaid
            ->map(function (PriceObservation $paid) use ($cheapest) {
                $best = $cheapest->get($paid->product_id);

                if (!$best || $best->store_id === $paid->store_id || $best->price_cents >= $paid->price_cents || $best->unit !== $paid->unit) {
                    return null;
                }

                return [
                    'product' => $this->product($paid),
                    'unit' => $paid->unit->value,
                    'paid_cents' => $paid->price_cents,
                    'paid_at' => ['id' => $paid->store->id, 'name' => $paid->store->name],
                    'cheapest_cents' => $best->price_cents,
                    'cheapest_at' => ['id' => $best->store->id, 'name' => $best->store->name],
                    'saving_cents' => $paid->price_cents - $best->price_cents,
                ];
            })
            ->filter()
            ->sortByDesc('saving_cents')
            ->take(5)
            ->values();
    }

    /**
     * What this month's shopping would have cost less if each item had been bought at the
     * cheapest store with a current price for it.
     */
    private function potentialSavingsThisMonth(): int
    {
        $bought = PriceObservation::where('user_id', $this->user->id)
            ->where('source', PriceSource::Receipt)
            ->where('observed_on', '>=', now()->startOfMonth())
            ->with('receiptItem')
            ->get();
        $cheapest = $this->cheapestCurrentPerProduct($bought->pluck('product_id')->unique()->all());

        return (int) round($bought->sum(function (PriceObservation $paid) use ($cheapest) {
            $best = $cheapest->get($paid->product_id);

            return $best && $best->unit === $paid->unit && $best->price_cents < $paid->price_cents
                ? ($paid->price_cents - $best->price_cents) * ($paid->receiptItem?->quantity ?? 1)
                : 0;
        }));
    }

    /**
     * The user's most recent receipt price for each product bought since the given date.
     */
    private function lastPaidPerProduct($since): Collection
    {
        return PriceObservation::where('user_id', $this->user->id)
            ->where('source', PriceSource::Receipt)
            ->where('observed_on', '>=', $since)
            ->with('product.brand', 'store')
            ->orderBy('observed_on')
            ->orderBy('id')
            ->get()
            ->keyBy('product_id');
    }

    /**
     * The cheapest latest price per product among stores with a current (not out-of-date) price.
     */
    private function cheapestCurrentPerProduct(array $productIds): Collection
    {
        return PriceObservation::latestPerStore($this->user, $productIds)
            ->with('store')
            ->get()
            ->reject->isStale()
            ->sortBy('price_cents')
            ->unique('product_id')
            ->keyBy('product_id');
    }

    private function product(PriceObservation $price): array
    {
        $product = $price->product;

        return [
            'id' => $product->id,
            'name' => trim(implode(' ', [$product->brand?->name, $product->name])),
            'size' => $product->size()?->label(),
        ];
    }
}
