<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('12345678'),
            'role' => fake()->randomElement(['admin', 'seller']),
            'phone' => '08'.fake()->numerify('##########'),
            'department' => fake()->randomElement(['Finance', 'HR', 'IT', 'General Affairs', 'Procurement']),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (): array => ['role' => 'admin']);
    }

    public function seller(): static
    {
        return $this->state(fn (): array => ['role' => 'seller']);
    }
}
