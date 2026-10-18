<?php

namespace App\Models;

use App\Support\Enums\ItemUnit;
use App\Support\Enums\PriceSource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded price for a product at a store on a day. Receipt prices are derived from
 * receipt items (see ReceiptItem::syncPriceObservation); online and promotion prices come later.
 */
class PriceObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'store_id', 'source', 'receipt_item_id', 'user_id', 'price_cents',
        'regular_price_cents', 'is_promotion', 'unit', 'observed_on', 'valid_until', 'source_url',
    ];

    protected function casts(): array
    {
        return [
            'source' => PriceSource::class,
            'unit' => ItemUnit::class,
            'is_promotion' => 'boolean',
            'observed_on' => 'date',
            'valid_until' => 'date',
        ];
    }

    /**
     * Prices recorded before this day are out of date: the shelf price may have changed since.
     */
    public static function staleBefore(): Carbon
    {
        return now()->subDays(config('pricing.stale_after_days'))->startOfDay();
    }

    public function isStale(): bool
    {
        return $this->observed_on->lt(static::staleBefore());
    }

    /**
     * Prices a user may see: their own receipts, plus prices that belong to nobody (online and
     * promotion prices, Phase 9). Other users' receipts stay private.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query->where('user_id', $user->id)->orWhereNull('user_id'));
    }

    /**
     * The most recent visible price for each product at each store (latest date, then latest
     * recorded), as one query for any number of products.
     */
    public static function latestPerStore(User $user, array $productIds): Builder
    {
        $ranked = static::query()
            ->visibleTo($user)
            ->whereIn('product_id', $productIds)
            ->select('price_observations.*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY product_id, store_id ORDER BY observed_on DESC, id DESC) AS recency');

        return static::query()->fromSub($ranked, 'price_observations')->where('recency', 1);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(ReceiptItem::class);
    }
}
