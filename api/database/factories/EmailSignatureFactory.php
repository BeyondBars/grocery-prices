<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailSignature>
 */
class EmailSignatureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail,
            'signature' => Str::random(40),
            'verified_at' => $this->faker->boolean(50) ? now() : null,
            'expires_at' => Carbon::now()->addDays(7)->toDateTimeString(),
        ];
    }
}
