<?php

namespace Database\Seeders;

use App\Models\CashBankAccount;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class CashBankAccountSeeder extends Seeder
{
    /**
     * Seed cash drawers, bank accounts, and payment gateway receivers.
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Kas Utama', 'type' => 'kas', 'method' => 'cash', 'account_number' => null, 'bank_name' => null, 'is_default' => true],
            ['name' => 'Kas Kecil', 'type' => 'kas', 'method' => 'cash', 'account_number' => null, 'bank_name' => null, 'is_default' => false],

            ['name' => 'Transfer Bank Manual', 'type' => 'bank', 'method' => 'bank', 'account_number' => '8871200455', 'bank_name' => 'Bank', 'is_default' => true],
            ['name' => 'BCA - Rekening Operasional', 'type' => 'bank', 'method' => 'bca', 'account_number' => '1234567890', 'bank_name' => 'Bank BCA', 'is_default' => true],
            ['name' => 'BCA - Rekening Tabungan', 'type' => 'bank', 'method' => 'bca', 'account_number' => '8831223456', 'bank_name' => 'Bank BCA', 'is_default' => false],
            ['name' => 'BNI - Rekening Bisnis', 'type' => 'bank', 'method' => 'bni', 'account_number' => '0987654321', 'bank_name' => 'Bank BNI', 'is_default' => true],
            ['name' => 'BRI - Rekening Penerimaan', 'type' => 'bank', 'method' => 'bri', 'account_number' => '0101122233', 'bank_name' => 'Bank BRI', 'is_default' => true],
            ['name' => 'Mandiri - Rekening Kas', 'type' => 'bank', 'method' => 'mandiri', 'account_number' => '1230007890123', 'bank_name' => 'Bank Mandiri', 'is_default' => true],
            ['name' => 'BSI - Tabungan Usaha', 'type' => 'bank', 'method' => 'bsi', 'account_number' => '7001234567', 'bank_name' => 'Bank Syariah Indonesia', 'is_default' => true],

            ['name' => 'QRIS - Pembayaran Statis', 'type' => 'bank', 'method' => 'qris', 'account_number' => 'ID1024398712345', 'bank_name' => 'QRIS', 'is_default' => true],

            ['name' => 'GoPay - Saldo Merchant', 'type' => 'bank', 'method' => 'gopay', 'account_number' => '081298001234', 'bank_name' => 'GoPay', 'is_default' => true],
            ['name' => 'OVO - Saldo Merchant', 'type' => 'bank', 'method' => 'ovo', 'account_number' => '081298005678', 'bank_name' => 'OVO', 'is_default' => true],
            ['name' => 'DANA - Saldo Merchant', 'type' => 'bank', 'method' => 'dana', 'account_number' => '081298009999', 'bank_name' => 'DANA', 'is_default' => true],
            ['name' => 'ShopeePay - Saldo Merchant', 'type' => 'bank', 'method' => 'shopeepay', 'account_number' => '081298001111', 'bank_name' => 'ShopeePay', 'is_default' => true],
        ];

        $methods = PaymentMethod::query()->pluck('id', 'code');

        foreach ($accounts as $account) {
            $methodId = $methods[$account['method']] ?? null;

            if ($methodId === null) {
                continue;
            }

            CashBankAccount::query()->updateOrCreate(
                ['name' => $account['name']],
                [
                    'type' => $account['type'],
                    'payment_method_id' => $methodId,
                    'account_number' => $account['account_number'],
                    'bank_name' => $account['bank_name'],
                    'is_default' => $account['is_default'],
                    'is_active' => true,
                ]
            );
        }
    }
}
