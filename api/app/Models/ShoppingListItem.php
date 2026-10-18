<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingListItem extends Model
{
    use HasFactory;

    protected $fillable = ['shopping_list_id', 'product_id', 'free_text', 'quantity', 'is_checked', 'position'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'is_checked' => 'boolean'];
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * What to show: the product's name, or the note the user typed.
     */
    public function label(): string
    {
        return $this->product
            ? trim(implode(' ', [$this->product->brand?->name, $this->product->name, $this->product->size()?->label()]))
            : (string) $this->free_text;
    }
}
