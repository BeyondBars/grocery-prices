<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\Enums\UserRole;
use App\Support\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => fake()->unique()->numerify('04########'),
            'status' => UserStatus::Enabled->value,
            'terms_accepted_at' => now(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function enabled()
    {
        return $this->state(['status' => UserStatus::Enabled->value]);
    }

    public function disabled()
    {
        return $this->state(['status' => UserStatus::Disabled->value]);
    }

    public function pending()
    {
        return $this->state(['status' => UserStatus::Pending->value, 'email_verified_at' => null]);
    }

    public function role(UserRole $role)
    {
        return $this->afterCreating(function (User $user) use ($role) {
            $user->assign($role->value);
        });
    }

    public function withTwoFactor(string $method = 'email')
    {
        return $this->state([
            'two_factor_required' => true,
            'two_factor_method_enabled' => $method,
            'two_factor_method_preferred' => $method,
        ]);
    }

    public function standardUser()
    {
        return $this->afterCreating(function (User $user) {
            $user->assign(UserRole::User->value);
        });
    }

    public function superAdminUser()
    {
        return $this->afterCreating(function (User $user) {
            // Abilities come from the role (synced by `php artisan abilities:refresh`).
            $user->assign(UserRole::SuperAdmin->value);
        });
    }
}
