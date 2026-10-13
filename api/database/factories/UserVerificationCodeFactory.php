<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserVerificationCode>
 */
class UserVerificationCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'method' => 'email',
            'code' => (string) random_int(100000, 999999),
        ];
    }
}
