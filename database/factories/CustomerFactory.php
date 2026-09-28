<?php

namespace Database\Factories;

use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_type' => CustomerType::Individual,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }

    /**
     * Pelanggan badan usaha dengan data perusahaan lengkap.
     */
    public function business(): static
    {
        return $this->state(fn (array $attributes): array => [
            'customer_type' => CustomerType::Business,
            'company_name' => fake()->company().' '.fake()->randomElement(['Jl', 'PT', 'CV']),
            'nik' => fake()->numerify('3##############'),
            'npwp' => fake()->numerify('##.###.###.#-###.###'),
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
            'postal_code' => '40115',
            'country' => 'Indonesia',
        ]);
    }
}
