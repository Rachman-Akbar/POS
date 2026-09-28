<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => null,
            'from_status' => null,
            'to_status' => 'pending',
            'notes' => 'Order dibuat.',
        ];
    }
}
