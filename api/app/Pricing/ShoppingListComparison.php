<?php

namespace App\Pricing;

use App\Models\PriceObservation;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What a shopping list costs at each store, from the latest price the user has seen there.
 *
 * A store only counts as "cheapest overall" if it has a price for every item that has a price
 * anywhere; otherwise it would win just by missing the expensive things. Each store lists what
 * it's missing. The split shop buys each item where it's cheapest.
 *
 * Like the product page, current prices are preferred: an item's out-of-date prices are only
 * used when it has no current ones.
 */
class ShoppingListComparison
{
    public function __construct(private ShoppingList $list, private User $user)
    {
    }

    public function toArray(): array
    {
        $items = $this->list->items()->with('product.brand')->get();
        $prices = $this->pricesByProduct($items->pluck('product_id')->filter()->unique()->values()->all());
        $stores = $prices->flatten(1)->pluck('store', 'store_id');

        $rows = $items->map(fn (ShoppingListItem $item) => $this->row($item, $prices->get($item->product_id, collect())));
        $priced = $rows->filter(fn ($row) => $row['prices']->isNotEmpty());

        $totals = $stores->map(fn ($store, $storeId) => $this->storeTotal($store, $priced, $storeId))
            ->sortBy([['missing_count', 'asc'], ['total_cents', 'asc']])
            ->values();
        $complete = $totals->where('missing_count', 0)->values();
        $best = $complete->first();
        $dearest = $complete->last();

        return [
            'items' => $rows->map(fn ($row) => [...$row, 'prices' => $row['prices']->values()])->values(),
            'stores' => $totals,
            'cheapest' => $best,
            'saving' => $complete->count() > 1 ? [
                'cents' => $dearest['total_cents'] - $best['total_cents'],
                'compared_with' => $dearest['store'],
            ] : null,
            'split' => $this->split($priced, $best),
            'unpriced' => $rows->filter(fn ($row) => $row['prices']->isEmpty())->pluck('label')->values(),
        ];
    }

    /**
     * For each product, its latest price at each store (current ones when it has any).
     */
    private function pricesByProduct(array $productIds): Collection
    {
        return PriceObservation::latestPerStore($this->user, $productIds)
            ->with('store')
            ->get()
            ->groupBy('product_id')
            ->map(function (Collection $latest) {
                $current = $latest->reject->isStale();

                return ($current->isNotEmpty() ? $current : $latest)->map(fn (PriceObservation $price) => [
                    'store_id' => $price->store_id,
                    'store' => ['id' => $price->store->id, 'name' => $price->store->name],
                    'price_cents' => $price->price_cents,
                    'unit' => $price->unit->value,
                    'observed_on' => $price->observed_on->toDateString(),
                ])->keyBy('store_id');
            });
    }

    private function row(ShoppingListItem $item, Collection $prices): array
    {
        $lineTotals = $prices->map(fn ($price) => [...$price, 'line_total_cents' => (int) round($price['price_cents'] * $item->quantity)]);

        return [
            'id' => $item->id,
            'label' => $item->label(),
            'quantity' => $item->quantity,
            'unit' => $prices->first()['unit'] ?? null,
            'prices' => $lineTotals->sortBy('line_total_cents'),
            'cheapest_store_id' => $lineTotals->sortBy('line_total_cents')->first()['store_id'] ?? null,
        ];
    }

    private function storeTotal(array $store, Collection $priced, int $storeId): array
    {
        $here = $priced->filter(fn ($row) => $row['prices']->has($storeId));
        $missing = $priced->reject(fn ($row) => $row['prices']->has($storeId));

        return [
            'store' => $store,
            'total_cents' => $here->sum(fn ($row) => $row['prices'][$storeId]['line_total_cents']),
            'priced_count' => $here->count(),
            'missing_count' => $missing->count(),
            'missing' => $missing->pluck('label')->values(),
        ];
    }

    /**
     * Buy each item at the store where it's cheapest (ties go to the cheapest single store).
     */
    private function split(Collection $priced, ?array $best): ?array
    {
        if ($priced->isEmpty()) {
            return null;
        }

        $choices = $priced->map(function ($row) use ($best) {
            $cheapest = $row['prices']->min('line_total_cents');
            $choice = $row['prices']->where('line_total_cents', $cheapest)
                ->sortBy(fn ($price) => $price['store_id'] === ($best['store']['id'] ?? null) ? 0 : 1)
                ->first();

            return ['label' => $row['label'], 'store' => $choice['store'], 'line_total_cents' => $choice['line_total_cents']];
        });

        $total = $choices->sum('line_total_cents');

        return [
            'stores' => $choices->groupBy(fn ($choice) => $choice['store']['id'])
                ->map(fn (Collection $group) => [
                    'store' => $group->first()['store'],
                    'items' => $group->pluck('label')->values(),
                    'total_cents' => $group->sum('line_total_cents'),
                ])
                ->sortByDesc(fn ($stop) => count($stop['items']))
                ->values(),
            'total_cents' => $total,
            'saving_cents' => $best ? $best['total_cents'] - $total : null,
        ];
    }
}
