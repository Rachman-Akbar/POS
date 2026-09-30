<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Satu-satunya role yang tidak boleh dihapus atau diubah bebas.
     */
    private const SUPER_ADMIN_SLUG = 'super-admin';

    /**
     * Role bawaan beserta permission default-nya.
     *
     * Bentuk tiap entri:
     * - `all_except`: semua permission dikurangi daftar yang dikecualikan.
     * - `only`: hanya permission tersebut yang diberikan.
     *
     * Daftar ini hanya titik awal, bukan daftar tertutup. Admin bebas menambah
     * role sendiri, mengubah nama dan permission role mana pun, lalu menghapus
     * role yang tidak dipakai. Hanya Super Admin yang terkunci karena ia
     * menjadi akar seluruh hak akses.
     *
     * @var array<string, array{description: string, all_except?: array<int, string>, only?: array<int, string>}>
     */
    private const ROLES = [
        'Super Admin' => [
            'description' => 'Akses penuh ke seluruh modul, termasuk mengelola kelompok hak akses.',
            'only' => [],
        ],
        'Admin' => [
            'description' => 'Mengelola seluruh modul POS kecuali menghapus kelompok hak akses.',
            'all_except' => ['role.delete'],
        ],
        'Supervisor' => [
            'description' => 'Mengubah master data, monitor operasional, dan mengoreksi transaksi kasir, tanpa menghapus data.',
            'all_except' => [
                'product.delete',
                'category.delete',
                'customer.delete',
                'cash.delete',
                'payment_method.delete',
                'user.delete',
                'role.delete',
            ],
        ],
        'Manager' => [
            'description' => 'Membaca seluruh data POS tanpa mengubah apa pun.',
            'only' => [
                'product.view',
                'category.view',
                'customer.view',
                'cash.view',
                'payment_method.view',
                'user.view',
                'accounting.view',
                'settings.view',
                'transaction.view',
                'transaction.view.all',
                'pos.view',
                'kitchen.view',
            ],
        ],
        'Kasir' => [
            'description' => 'Melayani penjualan di kasir, termasuk pembayaran dan diskon.',
            'only' => [
                'settings.view',
                'product.view',
                'product.favorite',
                'category.view',
                'customer.view',
                'customer.create',
                'cash.view',
                'payment_method.view',
                'transaction.view',
                'transaction.create',
                'transaction.update',
                'pos.view',
                'pos.sell',
                'pos.discount',
                'kitchen.view',
            ],
        ],
        'Pelayan' => [
            'description' => 'Mencatat dan menyelesaikan pesanan dine in, tanpa pembayaran.',
            'only' => [
                'settings.view',
                'product.view',
                'product.favorite',
                'customer.view',
                'transaction.view',
                'transaction.create',
                'transaction.update',
                'kitchen.view',
            ],
        ],
        'Dapur' => [
            'description' => 'Melihat antrean dan mengubah status produksi pesanan.',
            'only' => [
                'settings.view',
                'product.view',
                'kitchen.view',
                'kitchen.update',
            ],
        ],
    ];

    public function run(): void
    {
        $all = PermissionCatalog::names();

        foreach (self::ROLES as $name => $config) {
            $slug = str($name)->slug()->toString();
            $role = Role::query()->where('slug', $slug)->first();

            if (! $role) {
                $this->createRole($name, $slug, $config, $all);

                continue;
            }

            // Super Admin selalu dijaga: ia harus aktif, terkunci, dan tidak
            // bergantung pada tabel permission sama sekali.
            if ($slug === self::SUPER_ADMIN_SLUG) {
                $role->forceFill([
                    'name' => $name,
                    'description' => $config['description'],
                    'is_active' => true,
                    'is_system' => true,
                ])->save();

                continue;
            }

            // Membebaskan sisa role bawaan dari hasil seeder versi lama, di mana
            // semuanya terkunci. Kustomisasi lain tetap dibiarkan apa adanya.
            if ($role->is_system) {
                $role->forceFill(['is_system' => false])->save();
            }

            $this->topUpWideRole($role, $config, $all);
        }
    }

    /**
     * Role baru menerima permission default sesuai definisinya.
     *
     * @param  array{description: string, all_except?: array<int, string>, only?: array<int, string>}  $config
     * @param  array<int, string>  $all
     */
    private function createRole(string $name, string $slug, array $config, array $all): void
    {
        $granted = $this->targetPermissions($config, $all);

        $role = Role::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $config['description'],
            'is_active' => true,
            'is_system' => $slug === self::SUPER_ADMIN_SLUG,
        ]);

        $role->permissions()->sync(
            Permission::query()->whereIn('name', $granted)->pluck('id')->all()
        );
    }

    /**
     * Lengkapi permission role yang didefinisikan `all_except`.
     *
     * Role `all_except` menyatakan niatnya secara terbuka: ia memegang seluruh
     * permission kecuali daftar yang dikecualikan. Tanpa perbaikan ini, setiap
     * permission baru di PermissionCatalog hanya masuk ke Super Admin — yang
     * diperiksa lewat bypass, bukan tabel permission — dan diam-diam hilang
     * dari Admin dan Supervisor. Gejalanya tidak terlihat: endpoint memberikan
     * 403 hanya pada sebagian akun, dan报告显示 fitur itu "tidak berfungsi".
     *
     * Yang ditambahkan hanya permission katalog yang belum dimiliki role.
     * Pencabutan tidak pernah dilakukan, jadi permission yang sengaja dibatasi
     * admin tetap utuh dan custom role miliknya sendiri tidak tersentuh.
     *
     * Role `only` sengaja tidak dilewati. Daftar `only` adalah templat awal,
     * bukan lantai akses: kalau permission yang sengaja dicabut admin ikut
     * ditambahkan kembali, Kasir atau Dapur bisa acquiring hak akses baru
     * tanpa pernah disetujui siapa pun. Untuk role `only`, tidak ada cara
     * membedakan "belum dibuat" dari "sengaja dibuang", jadi menghormati
     * keputusan admin lebih penting daripada kepraktisan.
     *
     * @param  array{description: string, all_except?: array<int, string>, only?: array<int, string>}  $config
     * @param  array<int, string>  $all
     */
    private function topUpWideRole(Role $role, array $config, array $all): void
    {
        if (! isset($config['all_except'])) {
            return;
        }

        $target = $this->targetPermissions($config, $all);
        $owned = $role->permissions()->pluck('name')->all();
        $missing = array_values(array_diff($target, $owned));

        if ($missing === []) {
            return;
        }

        $role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('name', $missing)->pluck('id')->all()
        );

        $this->command?->info(sprintf(
            '  %s: +%d permission katalog (%s)',
            $role->name,
            count($missing),
            implode(', ', $missing),
        ));
    }

    /**
     * @param  array{description: string, all_except?: array<int, string>, only?: array<int, string>}  $config
     * @param  array<int, string>  $all
     * @return array<int, string>
     */
    private function targetPermissions(array $config, array $all): array
    {
        return isset($config['all_except'])
            ? array_values(array_diff($all, $config['all_except']))
            : ($config['only'] ?? []);
    }
}
