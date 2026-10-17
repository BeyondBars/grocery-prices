<?php

namespace Database\Factories;

use App\Support\Enums\SizeUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucwords($this->faker->unique()->words(3, true)),
            'size_amount' => $this->faker->randomElement([500, 1000, 2000]),
            'size_unit' => SizeUnit::Millilitres,
            'pack_count' => 1,
        ];
    }
}
