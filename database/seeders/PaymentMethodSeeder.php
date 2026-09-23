<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Seed the payment methods with their MDR rates.
     */
    public function run(): void
    {
        $methods = [
            ['code' => 'cash', 'name' => 'Tunai', 'mdr_rate' => 0.0000],
            ['code' => 'bank', 'name' => 'Transfer Bank', 'mdr_rate' => 0.0030],
            ['code' => 'qris', 'name' => 'QRIS', 'mdr_rate' => 0.0070],
        ];

        foreach ($methods as $method) {
            PaymentMethod::query()->updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}
