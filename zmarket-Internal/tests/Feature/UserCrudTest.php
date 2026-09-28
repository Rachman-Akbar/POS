<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_users_but_cannot_delete_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/admin/users', [
            'name' => 'Seller Satu',
            'email' => 'seller1@company.local',
            'password' => 'seller12345',
            'role' => 'seller',
            'phone' => '081200001111',
            'department' => 'Procurement',
            'is_active' => true,
        ]);

        $create->assertCreated()->assertJsonPath('data.role', 'seller');
        $sellerId = $create->json('data.id');

        $this->putJson("/api/admin/users/{$sellerId}", [
            'name' => 'Seller Update',
            'email' => 'seller1@company.local',
            'password' => null,
            'role' => 'seller',
            'phone' => null,
            'department' => 'Operations',
            'is_active' => true,
        ])->assertOk();

        $this->deleteJson("/api/admin/users/{$admin->id}")->assertUnprocessable();
        $this->deleteJson("/api/admin/users/{$sellerId}")->assertNoContent();
    }
}
