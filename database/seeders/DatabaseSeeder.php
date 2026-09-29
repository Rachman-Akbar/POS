<?php

namespace Database\Seeders;

use App\Models\User;
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
        $this->seedUsers();
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

    /**
     * AkunStaff awal. Password default: `password`.
     */
    private function seedUsers(): void
    {
        $password = 'password';

        foreach ([
            ['name' => 'Ahmad Fauzi', 'email' => 'ahmad.fauzi@warungnusantara.id', 'role' => 'admin'],
            ['name' => 'Siti Rahayu', 'email' => 'siti.rahayu@warungnusantara.id', 'role' => 'cashier'],
            ['name' => 'Budi Santoso', 'email' => 'budi.santoso@warungnusantara.id', 'role' => 'cashier'],
            ['name' => 'Rina Wulandari', 'email' => 'rina.wulandari@warungnusantara.id', 'role' => 'waiter'],
            ['name' => 'Agus Prasetyo', 'email' => 'agus.prasetyo@warungnusantara.id', 'role' => 'kitchen'],
            ['name' => 'Dewi Lestari', 'email' => 'dewi.lestari@warungnusantara.id', 'role' => 'kitchen'],
        ] as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                ['name' => $user['name'], 'role' => $user['role'], 'password' => $password]
            );
        }
    }
}
