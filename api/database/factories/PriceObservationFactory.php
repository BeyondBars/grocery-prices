<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
use App\Support\Enums\ItemUnit;
use App\Support\Enums\PriceSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceObservation>
 */
class PriceObservationFactory extends Factory
{
    public function definition(): array
    {
        $price = $this->faker->numberBetween(100, 1500);

        return [
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'source' => PriceSource::Receipt,
            'price_cents' => $price,
            'regular_price_cents' => $price,
            'is_promotion' => false,
            'unit' => ItemUnit::Each,
            'observed_on' => now()->subDays($this->faker->numberBetween(1, 30)),
        ];
    }
}
