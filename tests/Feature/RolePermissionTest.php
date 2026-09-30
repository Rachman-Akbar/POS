<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use SplFileInfo;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Urutan modul mengikuti kebutuhan operasional (transaksi & kasir dulu), jadi
     * test ini tidak mengunci indeks tertentu. Yang dijaga: setiap modul punya
     * label yang bisa dibaca admin dan tidak ada modul tanpa permission.
     */
    public function test_permission_catalog_is_grouped_by_module(): void
    {
        $this->actingAsSuperAdmin();

        $modules = $this->getJson('/api/admin/permissions')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['key', 'label', 'permissions' => [['name', 'label']]],
                ],
                'meta' => ['total'],
            ])
            ->json('data');

        $this->assertNotEmpty($modules);

        foreach ($modules as $module) {
            $this->assertArrayHasKey($module['key'], PermissionCatalog::MODULE_LABELS);
            $this->assertSame(PermissionCatalog::MODULE_LABELS[$module['key']], $module['label']);
            $this->assertNotEmpty($module['permissions'], "Modul {$module['key']} punya label tapi tanpa permission.");
        }
    }

    public function test_every_catalog_permission_is_covered_by_the_endpoint(): void
    {
        $this->actingAsSuperAdmin();

        $names = collect($this->getJson('/api/admin/permissions')->json('data'))
            ->flatMap(fn (array $module): array => array_column($module['permissions'], 'name'))
            ->all();

        sort($names);
        $expected = PermissionCatalog::names();
        sort($expected);

        $this->assertSame($expected, $names);
    }

    /**
     * Permission yang tidak dijaga route/controller/service mana pun akan tampil
     * sebagai checkbox di UI kelompok hak akses, lalu diam-diam tidak memberi
     * efek apa pun saat admin menyimpannya. Test ini menjaga katalog tetap jujur.
     *
     * Permission bisa ditegakkan di tiga tempat: middleware `permission:` pada
     * route, pemeriksaan `hasPermission()` di dalam controller untuk kasus
     * kondisional seperti diskon, dan pemeriksaan scope di service.
     */
    public function test_every_catalog_permission_is_enforced(): void
    {
        $fromRoutes = collect(RouteFacade::getRoutes())
            ->flatMap(fn (Route $route): array => $route->gatherMiddleware())
            ->implode(' ');

        $fromApp = collect([
            app_path('Http/Controllers'),
            app_path('Services'),
            app_path('Policies'),
        ])
            ->filter(fn (string $path): bool => is_dir($path))
            ->flatMap(fn (string $path): array => iterator_to_array(File::allFiles($path)))
            ->map(fn (SplFileInfo $file): string => (string) file_get_contents($file->getPathname()))
            ->implode(' ');

        $unenforced = array_values(array_filter(
            PermissionCatalog::names(),
            fn (string $name): bool => ! str_contains($fromRoutes, "permission:{$name}")
                && ! preg_match("/hasPermission\(\s*'".preg_quote($name, '/')."'\s*\)/", $fromApp),
        ));

        $this->assertSame(
            [],
            $unenforced,
            'Permission ini belum dijaga route, controller, atau service mana pun: '.implode(', ', $unenforced),
        );
    }

    public function test_role_can_be_created_with_permissions(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->actingAsSuperAdmin();

        $this->postJson('/api/admin/roles', [
            'name' => 'Kasir Toko',
            'description' => 'Kasir toko',
            'permissions' => ['product.create', 'product.view'],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Kasir Toko')
            ->assertJsonPath('data.slug', 'kasir-toko')
            ->assertJsonPath('data.permissions_count', 2);

        $role = Role::where('name', 'Kasir Toko')->firstOrFail();

        $this->assertSame(
            ['product.create', 'product.view'],
            $role->permissions()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_role_rejects_unknown_permission(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/admin/roles', [
            'name' => 'Nilai Ditolak',
            'permissions' => ['product.super'],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
    }

    public function test_role_requires_unique_name(): void
    {
        $this->actingAsSuperAdmin();
        $this->roleWith('kasir', ['product.create'], 'Kasir');

        $this->postJson('/api/admin/roles', ['name' => 'Kasir'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_role_permissions_can_be_replaced(): void
    {
        $this->actingAsSuperAdmin();
        $role = $this->roleWith('kasir', ['product.create', 'product.view'], 'Kasir');

        $this->putJson("/api/admin/roles/{$role->id}", [
            'permissions' => ['product.view', 'category.view'],
        ])->assertOk()->assertJsonPath('data.permissions_count', 2);

        $this->assertSame(
            ['category.view', 'product.view'],
            $role->fresh()->permissions()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $this->actingAsSuperAdmin();
        $role = $this->roleWith('kasir', ['product.create'], 'Kasir');
        User::factory()->create()->syncRoles([$role->id]);

        $this->deleteJson("/api/admin/roles/{$role->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Role masih dipakai 1 user. Pindahkan user terlebih dahulu.');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $this->actingAsSuperAdmin();
        $role = Role::create([
            'name' => 'Sistem Akuntansi',
            'slug' => 'akuntansi',
            'is_active' => true,
            'is_system' => true,
        ]);

        $this->deleteJson("/api/admin/roles/{$role->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Role bawaan sistem tidak dapat dihapus. Nonaktifkan bila tidak dipakai.');
    }

    public function test_unused_role_can_be_deleted(): void
    {
        $this->actingAsSuperAdmin();
        $role = $this->roleWith('sementara', ['product.view'], 'Sementara');

        $this->deleteJson("/api/admin/roles/{$role->id}")->assertNoContent();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_non_super_admin_cannot_edit_super_admin_role(): void
    {
        $adminRole = $this->roleWith('admin', ['role.view', 'role.update'], 'Admin');
        $actor = User::factory()->create(['role' => 'cashier']);
        $actor->syncRoles([$adminRole->id]);

        $superAdmin = Role::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'is_active' => true,
            'is_system' => true,
        ]);

        Sanctum::actingAs($actor->fresh());

        $this->putJson("/api/admin/roles/{$superAdmin->id}", ['description' => 'dicoba'])
            ->assertForbidden();
    }

    public function test_user_without_permission_receives_403(): void
    {
        $category = Category::create(['name' => 'Makanan', 'sort_order' => 1, 'is_active' => true]);
        $role = $this->roleWith('dapur', ['category.view', 'category.update'], 'Dapur');
        $user = User::factory()->create(['role' => 'kitchen']);
        $user->syncRoles([$role->id]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/admin/products')->assertForbidden();
        $this->postJson('/api/admin/products', [])->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
        $this->deleteJson("/api/admin/categories/{$category->id}")->assertForbidden();
        $this->getJson('/api/admin/settings')->assertForbidden();
    }

    public function test_missing_permission_is_reported(): void
    {
        $role = $this->roleWith('kasir', ['product.create'], 'Kasir');
        $user = User::factory()->create(['role' => 'cashier']);
        $user->syncRoles([$role->id]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/admin/products')
            ->assertForbidden()
            ->assertJsonPath('missing_permission', 'product.view');
    }

    public function test_user_with_permission_passes_the_check(): void
    {
        $role = $this->roleWith('admin', ['product.view', 'category.view', 'settings.view'], 'Admin');
        $user = User::factory()->create(['role' => 'cashier']);
        $user->syncRoles([$role->id]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/admin/products')->assertOk();
        $this->getJson('/api/admin/categories')->assertOk();
        $this->getJson('/api/admin/settings')->assertOk();
    }

    public function test_permission_on_inactive_role_is_ignored(): void
    {
        $role = $this->roleWith('admin', ['product.view'], 'Admin');
        $user = User::factory()->create(['role' => 'cashier']);
        $user->syncRoles([$role->id]);

        $role->forceFill(['is_active' => false])->save();

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/admin/products')->assertForbidden();
    }

    public function test_super_admin_bypasses_permission_check(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/admin/roles')->assertOk();
        $this->getJson('/api/admin/permissions')->assertOk();
    }

    /**
     * Kolom `users.role` warisan pernah memakai nilai `admin` untuk menandai orang
     * yang punya akses penuh. Kalau nilai itu langsung dianggap Super Admin, maka
     * setiap akun Admin dan Supervisor ikut jadi Super Admin: batas
     * `transaction.view.all` jadi tidak berlaku dan permission yang sengaja
     * ditahan (mis. `role.delete`) tetap bisa dipakai.
     */
    public function test_legacy_admin_role_does_not_grant_super_admin_access(): void
    {
        $adminRole = $this->roleWith('admin', ['product.view'], 'Admin');
        $legacy = User::factory()->create(['role' => 'admin']);
        $legacy->syncRoles([$adminRole->id]);

        $this->assertFalse($legacy->fresh()->isSuperAdmin());
        $this->assertTrue($legacy->fresh()->hasPermission('product.view'));
        $this->assertFalse($legacy->fresh()->hasPermission('product.delete'));

        // Lewat API, permission yang tidak dipegang tetap ditolak.
        Sanctum::actingAs($legacy->fresh());
        $this->getJson('/api/admin/roles')->assertForbidden();

        // Role `super-admin` tetap jalur satu-satunya ke akses penuh.
        $superAdminRole = $this->roleWith('super-admin', ['product.view'], 'Super Admin');
        $legacy->syncRoles([$superAdminRole->id]);

        $this->assertTrue($legacy->fresh()->isSuperAdmin());
        $this->assertTrue($legacy->fresh()->hasPermission('product.delete'));
    }

    /**
     * User lama yang belum punya role sama sekali masih bergantung pada kolom
     * `role`, jadi jaring pengamannya harus tetap berlaku untuk mereka.
     */
    public function test_user_without_any_role_falls_back_to_the_legacy_column(): void
    {
        $legacy = User::factory()->create(['role' => 'admin']);

        $this->assertTrue($legacy->fresh()->isSuperAdmin());

        $plain = User::factory()->create(['role' => 'cashier']);

        $this->assertFalse($plain->fresh()->isSuperAdmin());
    }

    public function test_role_list_includes_user_and_permission_counts(): void
    {
        $this->actingAsSuperAdmin();
        $role = $this->roleWith('manajer', ['product.view', 'category.view'], 'Manajer');
        User::factory()->count(2)->create()->each(fn (User $user) => $user->syncRoles([$role->id]));

        $this->getJson('/api/admin/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Manajer', 'users_count' => 2, 'permissions_count' => 2]);
    }

    public function test_role_and_permission_changes_are_audited(): void
    {
        $this->actingAsSuperAdmin();
        $role = $this->roleWith('diaudit', ['product.view'], 'Diaudit');

        $this->putJson("/api/admin/roles/{$role->id}", [
            'name' => 'Diaudit Baru',
            'permissions' => ['product.view', 'product.create'],
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['module' => 'role', 'action' => 'update']);

        $this->deleteJson("/api/admin/roles/{$role->id}")->assertNoContent();

        $log = AuditLog::where('module', 'role')->where('action', 'delete')->firstOrFail();

        $this->assertSame('Menghapus kelompok hak akses Diaudit Baru.', $log->description);
        $this->assertNotNull($log->user_id);
    }

    public function test_marking_favorite_requires_permission(): void
    {
        $this->seed(PermissionSeeder::class);
        $product = Product::factory()->create();

        // Membaca katalog tetap publik, menandai favorit adalah perubahan data.
        $this->patchJson("/api/products/{$product->id}/favorite")->assertUnauthorized();

        $role = $this->roleWith('pembaca', ['product.view'], 'Pembaca');
        $user = User::factory()->create(['role' => 'cashier']);
        $user->syncRoles([$role->id]);
        Sanctum::actingAs($user->fresh());

        $this->patchJson("/api/products/{$product->id}/favorite")
            ->assertForbidden()
            ->assertJsonPath('missing_permission', 'product.favorite');

        $user->syncRoles([
            $this->roleWith('pengatur', ['product.favorite'], 'Pengatur')->id,
        ]);
        Sanctum::actingAs($user->fresh());

        $this->patchJson("/api/products/{$product->id}/favorite")->assertOk();
    }

    /**
     * Role bawaan harus benar-benar bisa menjalankan halaman yang menjadi
     * tugasnya. Kalau permission default kurang, kasir login tapi terhenti di
     * tengah transaksi, dan itu baru ketahuan saat shift berikutnya.
     *
     * @return array<int, array{0: string, 1: array<int, string>}>
     */
    public static function operationalExpectations(): array
    {
        return [
            ['kasir', [
                'product.view',
                'settings.view',
                'transaction.view',
                'transaction.create',
                'transaction.update',
                'pos.view',
                'pos.sell',
                'pos.discount',
                'kitchen.view',
                'customer.view',
                'customer.create',
            ]],
            ['pelayan', [
                'product.view',
                'settings.view',
                'transaction.view',
                'transaction.create',
                'transaction.update',
                'kitchen.view',
            ]],
            ['dapur', [
                'settings.view',
                'product.view',
                'kitchen.view',
                'kitchen.update',
            ]],
        ];
    }

    /**
     * @param  array<int, string>  $expected
     */
    #[DataProvider('operationalExpectations')]
    public function test_seeded_operational_roles_can_run_their_page(string $slug, array $expected): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $granted = Role::query()
            ->where('slug', $slug)
            ->firstOrFail()
            ->permissions()
            ->pluck('name')
            ->all();

        $missing = array_values(array_diff($expected, $granted));

        $this->assertSame([], $missing, "Role {$slug} belum punya: ".implode(', ', $missing));
    }

    public function test_seeded_roles_stay_customisable(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        // Hanya Super Admin yang terkunci; sisanya bebas diubah atau dihapus.
        $this->assertSame(1, Role::query()->where('is_system', true)->count());
        $this->assertDatabaseHas('roles', ['slug' => 'super-admin', 'is_system' => true]);

        $kasir = Role::query()->where('slug', 'kasir')->firstOrFail();
        $this->assertFalse($kasir->is_system);
    }

    public function test_seeding_does_not_overwrite_customised_role_permissions(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $kasir = Role::query()->where('slug', 'kasir')->firstOrFail();
        $kasir->permissions()->sync(
            Permission::query()->where('name', 'product.create')->pluck('id')
        );

        // Menjalankan seeder lagi adalah hal yang wajar di lingkungan dev,
        // jadi hak akses yang sudah disesuaikan admin tidak boleh ditimpa.
        $this->seed(RoleSeeder::class);

        $this->assertSame(
            ['product.create'],
            $kasir->fresh()->permissions->pluck('name')->all(),
        );
    }

    /**
     * Role `all_except` menyatakan niatnya secara terbuka, jadi permission katalog
     * yang belum dimiliki harus dilengkapi saat seeding. Tanpa ini, permission
     * baru hanya berlaku untuk Super Admin — yang diperiksa lewat bypass, bukan
     * tabel permission — dan 403-nya muncul tanpa jejak di akun Admin.
     */
    public function test_seeding_tops_up_wide_roles_without_revoking_anything(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $admin = Role::query()->where('slug', 'admin')->firstOrFail();

        // Simulasikan role yang dibuat sebelum katalog punya permission koreksi.
        $admin->permissions()->sync(
            Permission::query()->whereIn('name', ['pos.view', 'product.view'])->pluck('id')
        );

        $this->seed(RoleSeeder::class);

        $expected = PermissionCatalog::names();
        sort($expected);
        $after = $admin->fresh()->permissions->pluck('name')->all();
        sort($after);

        // Role `all_except` berakhir memegang seluruh katalog kecuali pengecualian.
        $this->assertSame(array_values(array_diff($expected, ['role.delete'])), array_values(array_diff($after, ['role.delete'])));

        // Permission koreksi yang baru masuk katalog ikut diberikan.
        $this->assertContains('transaction.void', $after);
        $this->assertContains('transaction.refund', $after);
        $this->assertContains('transaction.view.all', $after);

        // Pengecualian `all_except` tidak pernah diberikan.
        $this->assertNotContains('role.delete', $after);
    }

    /**
     * Yang dijaga: permission yang ada tidak pernah hilang, termasuk yang
     * sengaja dicabut admin dari daftar default.
     */
    public function test_seeding_never_revokes_a_permission_a_wide_role_already_had(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $supervisor = Role::query()->where('slug', 'supervisor')->firstOrFail();
        $supervisor->permissions()->sync(
            Permission::query()->whereIn('name', ['product.delete', 'pos.view'])->pluck('id')
        );

        $this->seed(RoleSeeder::class);

        $after = $supervisor->fresh()->permissions->pluck('name')->all();

        // `product.delete` ada di pengecualian, tapi sudah dimiliki role dan
        // tidak dicabut — pencabutan adalah keputusan admin, bukan seeding.
        $this->assertContains('product.delete', $after);
        $this->assertContains('transaction.void', $after);
    }

    /**
     * Role `only` punya daftar permission sebagai templat awal, bukan lantai
     * akses. Menambahkan otomatis di sana berarti permission yang sengaja
     * dicabut admin akan dikembalikan, dan Kasir bisa mendapat hak akses baru
     * tanpa pernah disetujui.
     */
    public function test_seeding_does_not_top_up_narrow_roles(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $dapur = Role::query()->where('slug', 'dapur')->firstOrFail();

        $dapur->permissions()->sync(
            Permission::query()->whereIn('name', ['kitchen.view'])->pluck('id')
        );

        $this->seed(RoleSeeder::class);

        $this->assertSame(
            ['kitchen.view'],
            $dapur->fresh()->permissions->pluck('name')->all(),
        );
    }
}
