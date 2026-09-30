<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        $this->call([
            ChartOfAccountSeeder::class,
            PaymentMethodSeeder::class,
            CashBankAccountSeeder::class,
            SettingSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            CustomerSeeder::class,
        ]);

        $this->call(SalesTransactionSeeder::class);

        $this->generateProductImages();
        $this->call(UserSeeder::class);
    }

    /**
     * Pembuat gambar produk.
     *
     * Dipanggil dari sini (bukan ProductSeeder) supaya seeder produk tetap
     * bebas efek samping berkas saat dipakai oleh test.
     */
    private function generateProductImages(): void
    {
        $this->command?->call('products:images', ['--prune' => true]);
    }
}
