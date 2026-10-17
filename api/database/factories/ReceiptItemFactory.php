<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Receipt;
use App\Support\Enums\ItemUnit;
use App\Support\Enums\MatchSource;
use App\Support\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReceiptItem>
 */
class ReceiptItemFactory extends Factory
{
    public function definition(): array
    {
        $price = $this->faker->numberBetween(100, 1500);

        return [
            'receipt_id' => Receipt::factory(),
            'line_number' => $this->faker->numberBetween(1, 40),
            'original_name' => strtoupper($this->faker->words(3, true)),
            'quantity' => 1,
            'unit' => ItemUnit::Each,
            'unit_price_cents' => $price,
            'total_price_cents' => $price,
            'discount_cents' => 0,
            'match_status' => MatchStatus::Pending,
        ];
    }

    public function matchedTo(Product $product): static
    {
        return $this->state([
            'product_id' => $product->id,
            'match_status' => MatchStatus::Confirmed,
            'match_confidence' => 100,
            'matched_by' => MatchSource::User,
            'matched_at' => now(),
        ]);
    }
}
