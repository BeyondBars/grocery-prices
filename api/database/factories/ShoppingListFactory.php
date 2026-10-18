<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShoppingList>
 */
class ShoppingListFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'name' => 'Weekly shop'];
    }
}
