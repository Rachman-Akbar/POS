<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Sinkronkan daftar permission dari PermissionCatalog. Idempotent: dijalankan
     * berulang hanya memperbarui label/deskripsi, tidak menambah duplikat.
     *
     * Permission yang sudah tidak ada lagi di katalog ikut dihapus supaya tidak
     * tampil sebagai checkbox yang tidak dijaga route mana pun. Baris pivot di
     * role_permissions ikut terhapus karena memakai cascade delete.
     */
    public function run(): void
    {
        foreach (PermissionCatalog::rows() as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission['name']],
                $permission,
            );
        }

        Permission::query()
            ->whereNotIn('name', PermissionCatalog::names())
            ->delete();
    }
}
