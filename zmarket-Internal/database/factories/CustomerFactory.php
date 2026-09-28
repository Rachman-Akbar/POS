<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'customer_type' => 'individual',
            'name' => fake()->name(),
            'phone' => '08'.fake()->numerify('##########'),
            'address' => fake()->address(),
            'nik' => null,
            'npwp' => null,
            'province' => null,
            'city' => null,
            'company_name' => null,
            'postal_code' => null,
            'country' => null,
        ];
    }

    public function business(): static
    {
        return $this->state(fn (): array => [
            'customer_type' => 'business',
            'nik' => fake()->numerify('################'),
            'npwp' => fake()->numerify('##.###.###.#-###.###'),
            'province' => fake()->randomElement(['DKI Jakarta', 'Jawa Barat', 'Jawa Tengah']),
            'city' => fake()->city(),
            'company_name' => fake()->company(),
            'postal_code' => fake()->postcode(),
            'country' => 'Indonesia',
        ]);
    }
}
