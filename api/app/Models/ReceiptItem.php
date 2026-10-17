<?php

namespace App\Models;

use App\Support\Enums\ItemUnit;
use App\Support\Enums\MatchSource;
use App\Support\Enums\MatchStatus;
use App\Support\Enums\PriceSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_id', 'line_number', 'original_name', 'normalized_name', 'sku',
        'quantity', 'unit', 'unit_price_cents', 'total_price_cents', 'discount_cents',
        'product_id', 'suggested_product_id', 'match_status', 'match_confidence',
        'matched_by', 'matched_by_user_id', 'matched_at', 'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'unit' => ItemUnit::class,
            'match_status' => MatchStatus::class,
            'matched_by' => MatchSource::class,
            'matched_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function suggestedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'suggested_product_id');
    }

    public function priceObservation(): HasOne
    {
        return $this->hasOne(PriceObservation::class);
    }

    /**
     * What was actually paid per unit (each, or per kg) after the line's discount, in cents.
     */
    public function paidUnitPriceCents(): int
    {
        $quantity = $this->quantity > 0 ? $this->quantity : 1;

        return (int) round(($this->total_price_cents - $this->discount_cents) / $quantity);
    }

    /**
     * Create, update or remove this line's price history entry so it always reflects the
     * current match. Called whenever the match, the printed values or the receipt header change.
     */
    public function syncPriceObservation(): void
    {
        $receipt = $this->receipt;

        if (!$this->countsTowardsPriceHistory()) {
            $this->priceObservation()->delete();

            return;
        }

        $regularPrice = $this->unit_price_cents ?? (int) round($this->total_price_cents / ($this->quantity ?: 1));

        PriceObservation::updateOrCreate(['receipt_item_id' => $this->id], [
            'product_id' => $this->product_id,
            'store_id' => $receipt->store_id,
            'user_id' => $receipt->user_id,
            'source' => PriceSource::Receipt,
            'price_cents' => $this->paidUnitPriceCents(),
            'regular_price_cents' => $regularPrice,
            'is_promotion' => $this->discount_cents > 0,
            'unit' => $this->unit,
            'observed_on' => $receipt->purchased_at->toDateString(),
        ]);
    }

    private function countsTowardsPriceHistory(): bool
    {
        $receipt = $this->receipt;

        return $this->product_id
            && $this->match_status->isMatched()
            && $this->total_price_cents > 0
            && $receipt->store_id
            && $receipt->purchased_at
            && !$receipt->trashed();
    }
}
