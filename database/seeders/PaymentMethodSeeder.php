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
            ['code' => 'cash', 'type' => 'kas', 'name' => 'Tunai', 'mdr_rate' => 0.0000],
            ['code' => 'bank', 'type' => 'bank', 'name' => 'Transfer Bank', 'mdr_rate' => 0.0015],
            ['code' => 'qris', 'type' => 'qris', 'name' => 'QRIS', 'mdr_rate' => 0.0070],
            ['code' => 'bca', 'type' => 'bank', 'name' => 'Bank BCA', 'mdr_rate' => 0.0015],
            ['code' => 'bni', 'type' => 'bank', 'name' => 'Bank BNI', 'mdr_rate' => 0.0015],
            ['code' => 'bri', 'type' => 'bank', 'name' => 'Bank BRI', 'mdr_rate' => 0.0015],
            ['code' => 'mandiri', 'type' => 'bank', 'name' => 'Bank Mandiri', 'mdr_rate' => 0.0015],
            ['code' => 'bsi', 'type' => 'bank', 'name' => 'Bank Syariah Indonesia', 'mdr_rate' => 0.0015],
            ['code' => 'gopay', 'type' => 'ewallet', 'name' => 'GoPay', 'mdr_rate' => 0.0070],
            ['code' => 'ovo', 'type' => 'ewallet', 'name' => 'OVO', 'mdr_rate' => 0.0070],
            ['code' => 'dana', 'type' => 'ewallet', 'name' => 'DANA', 'mdr_rate' => 0.0070],
            ['code' => 'shopeepay', 'type' => 'ewallet', 'name' => 'ShopeePay', 'mdr_rate' => 0.0070],
        ];

        foreach ($methods as $method) {
            PaymentMethod::query()->updateOrCreate(
                ['code' => $method['code']],
                $method + ['is_active' => true]
            );
        }
    }
}
