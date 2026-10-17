<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use App\Support\Enums\ReceiptStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Receipt>
 */
class ReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'store_id' => Store::factory(),
            'purchased_at' => $this->faker->dateTimeBetween('-60 days'),
            'total_cents' => $this->faker->numberBetween(500, 20000),
            'status' => ReceiptStatus::Completed,
        ];
    }
}
