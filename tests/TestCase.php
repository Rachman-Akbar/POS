<?php

namespace Tests;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Collection;

abstract class TestCase extends BaseTestCase
{
    /**
     * User dengan role Super Admin, dipakai test yang butuh akses penuh.
     */
    protected function superAdmin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['role' => 'admin', 'is_active' => true]);

        $role = Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'is_active' => true, 'is_system' => true],
        );

        $user->syncRoles([$role->id]);

        return $user->fresh();
    }

    protected function actingAsSuperAdmin(array $attributes = []): User
    {
        $user = $this->superAdmin($attributes);
        $this->actingAs($user);

        return $user;
    }

    /**
     * Seed permission dari katalog lalu buat role dengan permission tertentu.
     *
     * Nama role dibedakan otomatis karena `roles.name` unik dan satu test bisa
     * membuat lebih dari satu role.
     *
     * @param  array<int, string>  $permissions
     */
    protected function roleWith(string $slug, array $permissions = [], ?string $name = null): Role
    {
        $this->seed(PermissionSeeder::class);

        $known = PermissionCatalog::names();

        foreach (array_values(array_diff($permissions, $known)) as $unknown) {
            throw new \InvalidArgumentException("Permission tidak dikenal: {$unknown}");
        }

        $role = Role::query()->create([
            'name' => $name ?? 'Uji '.$this->counter(),
            'slug' => $slug,
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->sync(
            Permission::query()->whereIn('name', $permissions)->pluck('id')->all()
        );

        return $role->fresh();
    }

    /**
     * @return Collection<int, string>
     */
    protected function permissionsOf(User $user): Collection
    {
        return $user->fresh()->permissionNames();
    }

    /**
     * User operasional yang punya role dengan permission tertentu.
     *
     * Semua endpoint POS (katalog, order, dapur, pembayaran) memakai
     * `auth:sanctum` + `permission`, jadi test alur kasir wajib memakai user yang
     * benar-benar punya role — `actingAs()` saja tidak lagi cukup.
     *
     * @param  array<int, string>  $permissions
     */
    protected function staff(array $permissions, array $attributes = []): User
    {
        $role = $this->roleWith('uji-'.$this->counter(), $permissions);

        $user = User::factory()->create($attributes + ['is_active' => true]);
        $user->syncRoles([$role->id]);

        return $user->fresh();
    }

    /**
     * Tambahkan role dengan permission tertentu ke user yang sudah ada.
     *
     * Role yang sudah ada tidak dilepas, jadi user bisa memegang beberapa role
     * sekaligus — persis seperti admin yang sedang shift di kasir: ia tetap
     * memegang `transaction.void` walaupun dibuatkan role kasir.
     *
     * @param  array<int, string>  $permissions
     */
    protected function grant(User $user, array $permissions): User
    {
        $role = $this->roleWith('uji-'.$this->counter(), $permissions);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user->fresh();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function actingAsStaff(array $permissions, array $attributes = []): User
    {
        $user = $this->staff($permissions, $attributes);
        $this->actingAs($user);

        return $user;
    }

    /**
     * Permission yang dibutuhkan satu staff untuk menjalankan alur POS harian.
     * Dipakai test order/pembayaran agar tidak mengulang daftar yang sama.
     *
     * @return array<int, string>
     */
    protected function cashierPermissions(): array
    {
        return [
            'settings.view',
            'product.view',
            'transaction.view',
            'transaction.create',
            'transaction.update',
            'pos.view',
            'pos.sell',
            'pos.discount',
            'kitchen.view',
            'customer.view',
            'customer.create',
        ];
    }

    /**
     * Pelayan membuat dan menyelesaikan pesanan dine in, tapi tidak boleh
     * menyentuh pembayaran atau master data.
     *
     * @return array<int, string>
     */
    protected function waiterPermissions(): array
    {
        return [
            'settings.view',
            'product.view',
            'transaction.view',
            'transaction.create',
            'transaction.update',
            'kitchen.view',
        ];
    }

    /**
     * Dapur hanya mengubah status produksi item.
     *
     * @return array<int, string>
     */
    protected function kitchenPermissions(): array
    {
        return [
            'settings.view',
            'kitchen.view',
            'kitchen.update',
        ];
    }

    private function counter(): int
    {
        static $count = 0;

        return ++$count;
    }
}
