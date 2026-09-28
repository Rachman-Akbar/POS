<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_with_session_authentication(): void
    {
        User::factory()->create([
            'email' => 'admin@company.local',
            'password' => 'admin12345',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->postJson('/api/admin/login', [
            'email' => 'admin@company.local',
            'password' => 'admin12345',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@company.local')
            ->assertJsonPath('user.role', 'admin');

        $this->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin@company.local');
    }
}
