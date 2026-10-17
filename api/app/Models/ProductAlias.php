<?php

namespace App\Models;

use App\Support\Enums\MatchSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAlias extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'store_id', 'normalized_text', 'sku', 'source', 'confirmed_by', 'times_seen'];

    protected function casts(): array
    {
        return ['source' => MatchSource::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
