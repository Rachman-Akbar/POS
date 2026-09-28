<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 25000, 5000000);

        return [
            'order_number' => 'INV-'.now()->format('Ymd').'-'.fake()->unique()->numerify('#####'),
            'customer_id' => null,
            'customer_type' => 'individual',
            'guest_email' => fake()->safeEmail(),
            'guest_name' => fake()->name(),
            'guest_phone' => '08'.fake()->numerify('##########'),
            'guest_address' => fake()->address(),
            'guest_nik' => null,
            'guest_npwp' => null,
            'guest_province' => null,
            'guest_city' => null,
            'guest_company_name' => null,
            'guest_postal_code' => null,
            'guest_country' => null,
            'guest_notes' => fake()->optional()->sentence(),
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
            'status' => 'pending',
            'payment_method' => 'internal_billing',
            'payment_status' => 'unpaid',
            'cancelled_at' => null,
            'cancel_reason' => null,
            'admin_notes' => null,
        ];
    }
}
