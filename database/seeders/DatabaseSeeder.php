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
            SettingSeeder::class,
            ProductSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(TestingSeeder::class);
        }

        $password = 'password';

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@pos.test',
            'role' => 'admin',
            'password' => $password,
        ]);

        User::factory()->create([
            'name' => 'Waiter',
            'email' => 'waiter@pos.test',
            'role' => 'waiter',
            'password' => $password,
        ]);

        User::factory()->create([
            'name' => 'Koki',
            'email' => 'koki@pos.test',
            'role' => 'kitchen',
            'password' => $password,
        ]);

        User::factory()->create([
            'name' => 'Kasir',
            'email' => 'kasir@pos.test',
            'role' => 'cashier',
            'password' => $password,
        ]);
    }
}
