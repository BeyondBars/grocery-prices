<?php

namespace App\Models;

use App\Support\Enums\SizeUnit;
use App\Support\Size;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'brand_id', 'category_id', 'variant', 'size_amount', 'size_unit', 'pack_count', 'barcode', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'size_amount' => 'float',
            'size_unit' => SizeUnit::class,
            'pack_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            $brand = $product->brand_id ? Brand::whereKey($product->brand_id)->value('name') : null;

            $product->match_key = static::normalise(implode(' ', [$brand, $product->name, $product->variant]));
        });
    }

    /**
     * Upper case, letters and digits only, single spaces. "Paul's Full-Cream" => "PAULS FULL CREAM".
     */
    public static function normalise(string $text): string
    {
        return Str::of($text)->upper()->remove("'")->replaceMatches('/[^A-Z0-9.]+/', ' ')->squish()->value();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ProductAlias::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(ReceiptItem::class);
    }

    public function priceObservations(): HasMany
    {
        return $this->hasMany(PriceObservation::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('merged_into_id');
    }

    /**
     * Size text as typed ("2 LT", "700g") as model attributes in base units.
     */
    public static function sizeAttributes(?string $size): array
    {
        $parsed = Size::parse($size);

        return ['size_amount' => $parsed?->amount, 'size_unit' => $parsed?->unit];
    }

    public function size(): ?Size
    {
        return $this->size_amount !== null && $this->size_unit ? new Size($this->size_amount, $this->size_unit) : null;
    }

    public function isInUse(): bool
    {
        return $this->receiptItems()->exists() || $this->priceObservations()->exists();
    }
}
