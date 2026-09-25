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

        foreach ([
            ['name' => 'Admin', 'email' => 'admin@pos.test', 'role' => 'admin'],
            ['name' => 'Waiter', 'email' => 'waiter@pos.test', 'role' => 'waiter'],
            ['name' => 'Koki', 'email' => 'koki@pos.test', 'role' => 'kitchen'],
            ['name' => 'Kasir', 'email' => 'kasir@pos.test', 'role' => 'cashier'],
        ] as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                ['name' => $user['name'], 'role' => $user['role'], 'password' => $password]
            );
        }
    }
}
