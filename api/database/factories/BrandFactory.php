<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Brand>
 */
class BrandFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->word());

        return ['name' => $name, 'aliases' => [strtoupper($name)]];
    }
}
