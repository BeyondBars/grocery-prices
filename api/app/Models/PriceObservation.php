<?php

namespace App\Models;

use App\Support\Enums\ItemUnit;
use App\Support\Enums\PriceSource;
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
