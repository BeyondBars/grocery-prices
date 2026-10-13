<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

abstract class BaseSeeder extends Seeder
{
    /**
     * Seed the main user.
     */
    public static function superAdminUser(): User
    {
        return once(fn () => User::factory()
            ->superAdminUser()
            ->enabled()
            ->create([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'mobile' => '0404040404',
                'email' => 'admin@example.com',
                'password' => 'Secret*12345'
            ]));
    }

    /**
     * Seed the main admin.
     */
    public static function standardUser(): User
    {
        return once(fn () => User::factory()
            ->standardUser()
            ->enabled()
            ->create([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'user@example.com',
                'password' => 'Secret*12345'
            ]));
    }
}
