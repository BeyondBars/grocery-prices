<?php

namespace App\Models;

use App\Support\Enums\ReceiptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Receipt extends Model
{
    use HasFactory;
    use Prunable;
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'store_id', 'store_location', 'purchased_at', 'receipt_number',
        'subtotal_cents', 'discount_cents', 'total_cents', 'currency', 'status',
        'file_path', 'file_mime', 'file_size', 'file_hash',
        'ocr_provider', 'ocr_text', 'parsed_data', 'failure_reason', 'processed_at',
    ];

    protected $hidden = ['ocr_text', 'file_path'];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'processed_at' => 'datetime',
            'status' => ReceiptStatus::class,
            'parsed_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // A deleted receipt must stop counting towards price history.
        static::deleted(function (Receipt $receipt) {
            PriceObservation::whereIn('receipt_item_id', $receipt->items()->select('id'))->delete();
        });

        static::restored(fn (Receipt $receipt) => $receipt->syncPriceObservations());

        static::forceDeleted(function (Receipt $receipt) {
            $receipt->file_path && Storage::disk(config('receipts.disk'))->delete($receipt->file_path);
        });
    }

    /**
     * Deleted receipts can be restored for 30 days, then they and their files are removed for good
     * (php artisan model:prune, scheduled daily).
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<', now()->subDays(30));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReceiptItem::class)->orderBy('line_number');
    }

    public function syncPriceObservations(): void
    {
        $this->items->each(fn (ReceiptItem $item) => $item->setRelation('receipt', $this)->syncPriceObservation());
    }
}
