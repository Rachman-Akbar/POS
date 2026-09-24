<?php

namespace Database\Factories;

use App\Models\CashBankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashBankAccount>
 */
class CashBankAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Kas Kecil', 'Kas Besar', 'Bank BCA', 'Bank Mandiri', 'QRIS']),
            'type' => fake()->randomElement(['kas', 'bank']),
            'account_number' => fake()->numerify('##########'),
            'bank_name' => fn (array $attributes) => $attributes['type'] === 'bank' ? fake()->randomElement(['BCA', 'Mandiri', 'BNI']) : null,
            'is_active' => true,
        ];
    }
}
