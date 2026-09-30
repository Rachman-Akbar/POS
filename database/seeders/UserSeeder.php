<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Password untuk akun development. Waibt dibaca dari env `POS_DEV_PASSWORD`
     * agar kredensial development tidak ikut ke production.
     */
    private const DEFAULT_PASSWORD = 'password';

    /**
     * Akun development beserta kelompok hak aksesnya. Kolom `role` lama ikut
     * diisi agar kode yang masih membacanya tetap konsisten.
     *
     * Login menerima nama pengguna maupun email, jadi setiap akun punya
     * `username` singkat yang enak diketik di kasir.
     *
     * @var array<int, array{name: string, username: string, email: string, role: string, slug: string}>
     */
    private const USERS = [
        [
            'name' => 'Ahmad Fauzi',
            'email' => 'ahmad.fauzi@warungnusantara.id',
            'username' => 'ahmad',
            'role' => 'admin',
            'slug' => 'super-admin',
        ],
        [
            'name' => 'Dewi Anggraini',
            'email' => 'dewi.anggraini@warungnusantara.id',
            'username' => 'dewi.admin',
            'role' => 'admin',
            'slug' => 'admin',
        ],
        [
            'name' => 'Bagus Prasetyo',
            'email' => 'bagus.prasetyo@warungnusantara.id',
            'username' => 'bagus',
            'role' => 'admin',
            'slug' => 'supervisor',
        ],
        [
            'name' => 'Maya Lestari',
            'email' => 'maya.lestari@warungnusantara.id',
            'username' => 'maya',
            'role' => 'admin',
            'slug' => 'manager',
        ],
        [
            'name' => 'Siti Rahayu',
            'email' => 'siti.rahayu@warungnusantara.id',
            'username' => 'siti',
            'role' => 'cashier',
            'slug' => 'kasir',
        ],
        [
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@warungnusantara.id',
            'username' => 'budi',
            'role' => 'cashier',
            'slug' => 'kasir',
        ],
        [
            'name' => 'Rina Wulandari',
            'email' => 'rina.wulandari@warungnusantara.id',
            'username' => 'rina',
            'role' => 'waiter',
            'slug' => 'pelayan',
        ],
        [
            'name' => 'Agus Prasetyo',
            'email' => 'agus.prasetyo@warungnusantara.id',
            'username' => 'agus',
            'role' => 'kitchen',
            'slug' => 'dapur',
        ],
        [
            'name' => 'Dewi Lestari',
            'email' => 'dewi.lestari@warungnusantara.id',
            'username' => 'dewi.dapur',
            'role' => 'kitchen',
            'slug' => 'dapur',
        ],
    ];

    public function run(): void
    {
        $password = $this->password();

        foreach (self::USERS as $data) {
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'role' => $data['role'],
                    'password' => $password,
                    'is_active' => true,
                ],
            );

            $role = Role::query()->where('slug', $data['slug'])->first();

            if ($role) {
                $user->syncRoles([$role->id]);
            }
        }
    }

    /**
     * Password development. Di production, password bawaan ditolak agar akun
     * hasil seeder tidak pernah aktif dengan kredensial yang diketahui publik.
     */
    private function password(): string
    {
        $password = (string) (env('POS_DEV_PASSWORD') ?: self::DEFAULT_PASSWORD);

        if (app()->environment('production') && $password === self::DEFAULT_PASSWORD) {
            throw new RuntimeException(
                'Set env POS_DEV_PASSWORD sebelum seeding pada environment production.'
            );
        }

        return Hash::make($password);
    }
}
