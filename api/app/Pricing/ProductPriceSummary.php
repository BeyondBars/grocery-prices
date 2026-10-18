<?php

namespace App\Pricing;

use App\Models\PriceObservation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Where a product is cheapest for a user: the latest price at each store, cheapest first, the
 * saving against the dearest store, and lowest / highest / average over the whole history.
 *
 * Products sold by weight and by the item are never compared with each other: the summary uses
 * the unit of the most recent price. Out-of-date prices are listed last and don't count towards
 * the cheapest store, the saving or the average (unless every price is out of date): an old
 * special must not claim a store is cheapest today.
 */
class ProductPriceSummary
{
    public function __construct(private Product $product, private User $user)
    {
    }

    public function toArray(): array
    {
        $history = PriceObservation::visibleTo($this->user)
            ->where('product_id', $this->product->id)
            ->with('store')
            ->orderBy('observed_on')
            ->orderBy('id')
            ->get();

        $unit = $history->last()?->unit;
        $history = $history->where('unit', $unit)->values();

        $latest = $this->latestPerStore($history);
        $current = $latest->where('is_stale', false)->whenEmpty(fn () => $latest)->values();
        $cheapest = $current->first();
        $dearest = $current->last();

        return [
            'unit' => $unit?->value,
            'stores' => $latest->values()->all(),
            'cheapest' => $cheapest,
            'saving' => $current->count() > 1 ? [
                'cents' => $dearest['price_cents'] - $cheapest['price_cents'],
                'compared_with' => $dearest['store'],
            ] : null,
            'stats' => $history->isEmpty() ? null : [
                'lowest' => $this->point($history->sortBy([['price_cents', 'asc'], ['observed_on', 'desc']])->first()),
                'highest' => $this->point($history->sortBy([['price_cents', 'desc'], ['observed_on', 'desc']])->first()),
                // The typical price now: the average of each store's latest price.
                'average_cents' => (int) round($current->avg('price_cents')),
                'average_recorded_cents' => (int) round($history->avg('price_cents')),
                'count' => $history->count(),
            ],
        ];
    }

    /**
     * Every price over time, grouped by store, for the chart.
     */
    public function history(): array
    {
        return PriceObservation::visibleTo($this->user)
            ->where('product_id', $this->product->id)
            ->with('store')
            ->orderBy('observed_on')
            ->orderBy('id')
            ->get()
            ->groupBy('store_id')
            ->map(fn (Collection $prices) => [
                'store' => ['id' => $prices->first()->store->id, 'name' => $prices->first()->store->name],
                'unit' => $prices->last()->unit->value,
                'points' => $prices->map(fn (PriceObservation $price) => [
                    'date' => $price->observed_on->toDateString(),
                    'price_cents' => $price->price_cents,
                    'is_promotion' => $price->is_promotion,
                ])->values(),
            ])
            ->sortBy('store.name')
            ->values()
            ->all();
    }

    /**
     * The latest price at each store with the one before it: current prices cheapest first,
     * then out-of-date ones.
     */
    private function latestPerStore(Collection $history): Collection
    {
        return $history->groupBy('store_id')
            ->map(function (Collection $prices) {
                $latest = $prices->last();
                $previous = $prices->slice(0, -1)->last();

                return [
                    ...$this->point($latest),
                    'regular_price_cents' => $latest->regular_price_cents,
                    'is_promotion' => $latest->is_promotion,
                    'previous_price_cents' => $previous?->price_cents,
                    'is_stale' => $latest->isStale(),
                ];
            })
            ->sortBy([['is_stale', 'asc'], ['price_cents', 'asc'], ['observed_on', 'desc']])
            ->values();
    }

    private function point(PriceObservation $price): array
    {
        return [
            'store' => ['id' => $price->store->id, 'name' => $price->store->name],
            'price_cents' => $price->price_cents,
            'observed_on' => $price->observed_on->toDateString(),
        ];
    }
}
