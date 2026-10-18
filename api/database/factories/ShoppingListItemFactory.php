<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ShoppingList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShoppingListItem>
 */
class ShoppingListItemFactory extends Factory
{
    public function definition(): array
    {
        return ['shopping_list_id' => ShoppingList::factory(), 'product_id' => Product::factory(), 'quantity' => 1];
    }
}
