<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    /**
     * Seed the chart of accounts.
     */
    public function run(): void
    {
        $accounts = [
            ['code' => '1100', 'name' => 'Piutang Usaha', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1200', 'name' => 'Persediaan Bahan Baku', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1300', 'name' => 'Kas', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1310', 'name' => 'Bank', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1320', 'name' => 'QRIS', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '2500', 'name' => 'PPN Keluaran', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '4000', 'name' => 'Pendapatan Penjualan', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '5100', 'name' => 'HPP', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5200', 'name' => 'Biaya MDR / Admin', 'type' => 'expense', 'normal_balance' => 'debit'],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::query()->updateOrCreate(
                ['code' => $account['code']],
                $account
            );
        }
    }
}
