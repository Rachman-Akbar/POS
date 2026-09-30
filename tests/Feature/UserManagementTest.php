<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_list_shows_roles_status_and_login_time(): void
    {
        $this->actingAsSuperAdmin(['name' => 'Zainal Abidin']);
        $role = $this->roleWith('kasir', ['product.create'], 'Kasir');
        $user = User::factory()->create(['name' => 'Siti Rahayu']);
        $user->syncRoles([$role->id]);
        $user->forceFill(['last_login_at' => now()->subMinutes(5)])->save();

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Siti Rahayu')
            ->assertJsonPath('data.0.roles.0.name', 'Kasir')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'role', 'is_active', 'roles', 'created_at', 'last_login_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_user_list_can_be_searched_and_filtered(): void
    {
        $this->actingAsSuperAdmin();
        $kasir = $this->roleWith('kasir', ['product.create'], 'Kasir');
        $dapur = $this->roleWith('dapur', ['category.view'], 'Dapur');

        User::factory()->create(['name' => 'Siti Rahayu'])->syncRoles([$kasir->id]);
        User::factory()->create(['name' => 'Agus Prasetyo'])->syncRoles([$dapur->id]);
        $inactive = User::factory()->inactive()->create(['name' => 'Nonaktif']);
        $inactive->syncRoles([$kasir->id]);

        $this->getJson('/api/admin/users?search=Rahayu')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Siti Rahayu');

        $this->getJson('/api/admin/users?role=dapur')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Agus Prasetyo');

        $this->getJson('/api/admin/users?status=inactive')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Nonaktif');
    }

    public function test_user_can_be_created_with_role(): void
    {
        $this->actingAsSuperAdmin();
        $role = $this->roleWith('kasir', ['product.create'], 'Kasir');

        $this->postJson('/api/admin/users', [
            'name' => 'Kasir Baru',
            'username' => 'kasir.baru',
            'email' => 'kasir.baru@pos.test',
            'password' => 'rahasia-kasir',
            'password_confirmation' => 'rahasia-kasir',
            'role_ids' => [$role->id],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Kasir Baru')
            ->assertJsonPath('data.roles.0.name', 'Kasir');

        $user = User::where('email', 'kasir.baru@pos.test')->firstOrFail();

        $this->assertTrue(Hash::check('rahasia-kasir', $user->password));
        $this->assertSame('cashier', $user->role, 'Kolom role lama harus tetap konsisten.');
        $this->assertDatabaseHas('user_roles', ['user_id' => $user->id, 'role_id' => $role->id]);
    }

    public function test_password_is_never_returned_plaintext(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Kasir Baru',
            'username' => 'kasir.baru',
            'email' => 'kasir.baru@pos.test',
            'password' => 'rahasia-kasir',
            'password_confirmation' => 'rahasia-kasir',
        ])->assertCreated();

        $this->assertStringNotContainsString('rahasia-kasir', $response->getContent());
        $this->assertStringNotContainsString('password', $response->json('data.name'));
    }

    public function test_user_creation_validates_input(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/admin/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'username', 'email', 'password']);

        User::factory()->create(['email' => 'ada@pos.test']);

        $this->postJson('/api/admin/users', [
            'name' => 'Duplikat',
            'username' => 'duplikat',
            'email' => 'ada@pos.test',
            'password' => 'rahasia-kasir',
            'password_confirmation' => 'rahasia-kasir',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/admin/users', [
            'name' => 'Pendek',
            'username' => 'pendek',
            'email' => 'pendek@pos.test',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_user_roles_can_be_replaced(): void
    {
        $this->actingAsSuperAdmin();
        $kasir = $this->roleWith('kasir', ['product.create'], 'Kasir');
        $supervisor = $this->roleWith('supervisor', ['product.view'], 'Supervisor');
        $user = User::factory()->create();
        $user->syncRoles([$kasir->id]);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Nama Baru',
            'role_ids' => [$supervisor->id],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.roles.0.name', 'Supervisor');

        $this->assertSame([$supervisor->id], $user->fresh()->roles->pluck('id')->all());
    }

    public function test_deactivating_user_revokes_their_tokens(): void
    {
        $actor = $this->actingAsSuperAdmin();
        $role = $this->roleWith('kasir', ['product.create'], 'Kasir');
        $user = User::factory()->create();
        $user->syncRoles([$role->id]);
        $user->createToken('pos-token');

        $this->assertSame(1, $user->tokens()->count());

        $this->putJson("/api/admin/users/{$user->id}", ['is_active' => false])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertFalse($user->fresh()->is_active);
        $this->assertNotSame($user->id, $actor->id);
    }

    public function test_user_cannot_deactivate_self(): void
    {
        $actor = $this->actingAsSuperAdmin();

        $this->putJson("/api/admin/users/{$actor->id}", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Anda tidak dapat menonaktifkan akun sendiri.');

        $this->assertTrue($actor->fresh()->is_active);
    }

    public function test_user_cannot_delete_self(): void
    {
        $actor = $this->actingAsSuperAdmin();

        $this->deleteJson("/api/admin/users/{$actor->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Anda tidak dapat menghapus akun sendiri.');

        $this->assertDatabaseHas('users', ['id' => $actor->id]);
    }

    public function test_super_admin_can_deactivate_another_super_admin(): void
    {
        $first = $this->superAdmin(['name' => 'Super Satu']);
        $second = $this->superAdmin(['name' => 'Super Dua']);
        Sanctum::actingAs($second);

        $this->putJson("/api/admin/users/{$first->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }

    public function test_super_admin_cannot_be_deleted_when_no_other_role_holder_exists(): void
    {
        // Admin hasil transisi lama (hanya kolom `role`, tanpa baris user_roles)
        // tetap boleh bertindak, tetapi penjaga "Super Admin terakhir" berlaku.
        $actor = User::factory()->create(['role' => 'admin']);
        $target = $this->superAdmin(['email' => 'boss@pos.test']);

        Sanctum::actingAs($actor);

        $this->deleteJson("/api/admin/users/{$target->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tidak dapat menghapus Super Admin terakhir.');

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_non_super_admin_cannot_grant_super_admin_role(): void
    {
        $adminRole = $this->roleWith('admin', ['user.view', 'user.create', 'user.update'], 'Admin');
        $superAdminRole = Role::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'is_active' => true,
            'is_system' => true,
        ]);
        $actor = User::factory()->create();
        $actor->syncRoles([$adminRole->id]);

        Sanctum::actingAs($actor->fresh());

        $this->postJson('/api/admin/users', [
            'name' => 'Calon Super Admin',
            'username' => 'calon',
            'email' => 'calon@pos.test',
            'password' => 'rahasia-kasir',
            'password_confirmation' => 'rahasia-kasir',
            'role_ids' => [$superAdminRole->id],
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'calon@pos.test']);
    }

    public function test_non_super_admin_cannot_modify_super_admin_user(): void
    {
        $adminRole = $this->roleWith('admin', ['user.view', 'user.update'], 'Admin');
        $actor = User::factory()->create();
        $actor->syncRoles([$adminRole->id]);
        $target = $this->superAdmin(['email' => 'boss@pos.test']);

        Sanctum::actingAs($actor->fresh());

        $this->putJson("/api/admin/users/{$target->id}", ['name' => 'Diubah'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Hanya Super Admin yang dapat mengubah user Super Admin.');

        $this->assertNotSame('Diubah', $target->fresh()->name);
    }

    public function test_inactive_role_cannot_be_assigned(): void
    {
        $this->actingAsSuperAdmin();
        $role = $this->roleWith('kasir', ['product.create'], 'Kasir');
        $role->forceFill(['is_active' => false])->save();

        $this->postJson('/api/admin/users', [
            'name' => 'Kasir Baru',
            'username' => 'kasir.baru',
            'email' => 'kasir.baru@pos.test',
            'password' => 'rahasia-kasir',
            'password_confirmation' => 'rahasia-kasir',
            'role_ids' => [$role->id],
        ])->assertUnprocessable();
    }

    public function test_user_can_be_deleted_when_allowed(): void
    {
        $this->actingAsSuperAdmin();
        $role = $this->roleWith('kasir', ['product.create'], 'Kasir');
        $user = User::factory()->create();
        $user->syncRoles([$role->id]);

        $this->deleteJson("/api/admin/users/{$user->id}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('user_roles', ['user_id' => $user->id]);
    }

    public function test_password_can_be_reset_and_revokes_sessions(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->create();
        $user->createToken('pos-token');

        $this->postJson("/api/admin/users/{$user->id}/reset-password", [
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('password-baru-123', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_password_reset_validates_strength(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->create();

        $this->postJson("/api/admin/users/{$user->id}/reset-password", [
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_user_management_is_audited(): void
    {
        $this->actingAsSuperAdmin();

        $user = $this->postJson('/api/admin/users', [
            'name' => 'Kasir Baru',
            'username' => 'kasir.baru',
            'email' => 'kasir.baru@pos.test',
            'password' => 'rahasia-kasir',
            'password_confirmation' => 'rahasia-kasir',
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'user',
            'action' => 'create',
            'record_id' => (string) $user['id'],
        ]);
    }

    public function test_user_endpoints_require_permission(): void
    {
        $role = $this->roleWith('dapur', ['category.view'], 'Dapur');
        $user = User::factory()->create();
        $user->syncRoles([$role->id]);

        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/admin/users')->assertForbidden();
        $this->postJson('/api/admin/users', [])->assertForbidden();
        $this->deleteJson('/api/admin/users/1')->assertForbidden();
    }
}
