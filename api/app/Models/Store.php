<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Store extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'abn', 'aliases', 'created_by'];

    protected function casts(): array
    {
        return ['aliases' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(fn (Store $store) => $store->slug = Str::slug($store->name));
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function priceObservations(): HasMany
    {
        return $this->hasMany(PriceObservation::class);
    }

    /**
     * Whether deleting the store would lose receipt or price history.
     */
    public function isInUse(): bool
    {
        return Receipt::withTrashed()->whereBelongsTo($this)->exists() || $this->priceObservations()->exists();
    }
}
