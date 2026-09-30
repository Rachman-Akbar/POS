<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_current_user(): void
    {
        $user = $this->superAdmin(['email' => 'admin@pos.test']);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@pos.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'admin@pos.test')
            ->assertJsonPath('user.is_super_admin', true)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'roles', 'permissions']]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $user = $this->superAdmin(['email' => 'admin@pos.test']);

        $this->postJson('/api/login', [
            'email' => 'admin@pos.test',
            'password' => 'salah-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('identity');

        $this->assertSame(0, $user->tokens()->count());
    }

    /**
     * Nama pengguna adalah cara login utama di kasir: mengetik email lengkap
     * di layar sentuh jauh lebih lambat dan mudah salah.
     */
    public function test_login_accepts_username(): void
    {
        $user = $this->superAdmin(['email' => 'admin@pos.test', 'username' => 'admin']);

        $this->postJson('/api/login', [
            'identity' => 'admin',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.username', 'admin')
            ->assertJsonPath('user.id', $user->id);

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_username_login_is_case_insensitive(): void
    {
        $this->superAdmin(['email' => 'admin@pos.test', 'username' => 'admin']);

        $this->postJson('/api/login', [
            'identity' => 'ADMIN',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.username', 'admin');
    }

    public function test_identity_still_accepts_an_email_address(): void
    {
        $this->superAdmin(['email' => 'admin@pos.test', 'username' => 'admin']);

        $this->postJson('/api/login', [
            'identity' => 'admin@pos.test',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.email', 'admin@pos.test');
    }

    /**
     * Klien yang belum migrasi masih mengirim `email`. Menolaknya akan
     * memutus login semua perangkat kasir yang sedang dipakai.
     */
    public function test_login_still_accepts_the_legacy_email_field(): void
    {
        $user = $this->superAdmin(['email' => 'admin@pos.test', 'username' => 'admin']);

        $this->postJson('/api/login', [
            'email' => 'admin@pos.test',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_login_does_not_reveal_whether_an_account_exists(): void
    {
        $this->superAdmin(['email' => 'ada@pos.test', 'username' => 'ada']);

        $unknown = $this->postJson('/api/login', [
            'identity' => 'entah-siapa',
            'password' => 'password',
        ])->assertUnprocessable();

        $wrongPassword = $this->postJson('/api/login', [
            'identity' => 'ada',
            'password' => 'password-keliru',
        ])->assertUnprocessable();

        $this->assertSame(
            $unknown->json('errors.identity.0'),
            $wrongPassword->json('errors.identity.0'),
            'Pesan untuk akun tidak ada dan password salah harus sama persis.',
        );
    }

    /**
     * Pembatasan percobaan login, supaya password bisa ditebak tanpa dibatasi.
     */
    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $user = $this->superAdmin(['email' => 'admin@pos.test', 'username' => 'admin']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'identity' => 'admin',
                'password' => 'salah-password',
            ])->assertUnprocessable()->assertJsonValidationErrors('identity');
        }

        $throttled = $this->postJson('/api/login', [
            'identity' => 'admin',
            // Password yang benar pun ditolak: kunci sudah terkunci.
            'password' => 'password',
        ])->assertTooManyRequests();

        $this->assertStringContainsString(
            'Terlalu banyak percobaan login',
            (string) $throttled->json('errors.identity.0'),
        );
        $this->assertGreaterThan(0, (int) $throttled->headers->get('Retry-After'));
        $this->assertSame(0, $user->tokens()->count());
    }

    /**
     * Kunci dibuat per identitas, jadi satu penyerang tidak bisa mengunci
     * seluruh kasir di outlet yang sama.
     */
    public function test_throttling_one_account_does_not_lock_out_another(): void
    {
        $this->superAdmin(['email' => 'admin@pos.test', 'username' => 'admin']);
        $kasir = $this->superAdmin(['email' => 'kasir@pos.test', 'username' => 'kasir']);

        foreach (range(1, 6) as $attempt) {
            $this->postJson('/api/login', [
                'identity' => 'admin',
                'password' => 'salah-password',
            ]);
        }

        $this->postJson('/api/login', [
            'identity' => 'kasir',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.id', $kasir->id);
    }

    public function test_successful_login_clears_the_previous_failed_attempts(): void
    {
        $this->superAdmin(['email' => 'admin@pos.test', 'username' => 'admin']);

        foreach (range(1, 4) as $attempt) {
            $this->postJson('/api/login', [
                'identity' => 'admin',
                'password' => 'salah-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'identity' => 'admin',
            'password' => 'password',
        ])->assertOk();

        // Kalau kunci tidak dibersihkan, lima percobaan lagi akan terkunci.
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'identity' => 'admin',
                'password' => 'password',
            ])->assertOk();
        }
    }

    public function test_login_rejects_inactive_user(): void
    {
        $this->superAdmin(['email' => 'admin@pos.test', 'is_active' => false]);

        $this->postJson('/api/login', [
            'email' => 'admin@pos.test',
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('identity');
    }

    public function test_login_requires_identity_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['identity', 'password']);
    }

    public function test_admin_endpoint_rejects_anonymous_request(): void
    {
        $this->getJson('/api/admin/settings')->assertUnauthorized();
        $this->getJson('/api/admin/products')->assertUnauthorized();
        $this->getJson('/api/admin/users')->assertUnauthorized();
        $this->getJson('/api/admin/roles')->assertUnauthorized();
        $this->getJson('/api/payment-methods')->assertUnauthorized();
        $this->getJson('/api/cash-bank-accounts')->assertUnauthorized();
    }

    public function test_write_endpoints_reject_anonymous_request(): void
    {
        $this->postJson('/api/admin/roles', ['name' => 'Kasir'])->assertUnauthorized();
        $this->postJson('/api/admin/users', [])->assertUnauthorized();
        $this->deleteJson('/api/admin/products/1')->assertUnauthorized();
    }

    /**
     * Halaman operasional menulis ke database (order, status dapur, pembayaran),
     * jadi membiarkannya terbuka berarti siapa pun yang tahu URL bisa mengubah
     * data tanpa jejak. Hanya `/api/login` yang boleh dipanggil anonim.
     */
    public function test_operational_routes_reject_anonymous_requests(): void
    {
        $this->getJson('/api/settings')->assertUnauthorized();
        $this->getJson('/api/products')->assertUnauthorized();
        $this->getJson('/api/orders')->assertUnauthorized();
        $this->getJson('/api/orders/transactions')->assertUnauthorized();
        $this->getJson('/api/kitchen/items')->assertUnauthorized();
        $this->getJson('/api/customers')->assertUnauthorized();
        $this->getJson('/api/payments/pending')->assertUnauthorized();

        $this->postJson('/api/orders', [])->assertUnauthorized();
        $this->postJson('/api/orders/draft', [])->assertUnauthorized();
        $this->postJson('/api/orders/1/complete')->assertUnauthorized();
        $this->postJson('/api/orders/1/finalize')->assertUnauthorized();
        $this->postJson('/api/customers', [])->assertUnauthorized();
        $this->patchJson('/api/kitchen/items/1/status', [])->assertUnauthorized();
        $this->postJson('/api/payments/orders/1/settle', [])->assertUnauthorized();
    }

    /**
     * Hanya login yang boleh dipanggil anonim. Group `api` sendiri bukan
     * jaminan apa-apa, jadi yang diperiksa justru ketiadaan `auth:sanctum`.
     */
    public function test_login_is_the_only_public_endpoint(): void
    {
        $publicApiRoutes = collect(RouteFacade::getRoutes())
            ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/'))
            ->reject(fn (Route $route): bool => in_array('auth:sanctum', $route->gatherMiddleware(), true))
            ->pluck('uri')
            ->unique()
            ->values()
            ->all();

        $this->assertSame(['api/login'], $publicApiRoutes);
    }

    public function test_me_returns_authenticated_user_with_permissions(): void
    {
        $role = $this->roleWith('kasir', ['product.view', 'product.create']);
        $user = User::factory()->create(['role' => 'cashier', 'username' => 'kasir']);
        $user->syncRoles([$role->id]);

        Sanctum::actingAs($user);

        $permissions = $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.username', 'kasir')
            ->assertJsonPath('user.is_super_admin', false)
            ->json('user.permissions');

        $this->assertEqualsCanonicalizing(['product.create', 'product.view'], $permissions);
        $this->assertNotContains('product.delete', $permissions);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer token-palsu')
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_bearer_token_grants_access_and_logout_revokes_it(): void
    {
        $user = $this->superAdmin();
        $token = $user->createToken('pos-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Guard auth di-cache antar request dalam satu test, jadi dilupakan
        // eksplisit agar request berikutnya benar-benar membaca token yang dicabut.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_deactivated_user_token_is_blocked(): void
    {
        $user = $this->superAdmin();
        $token = $user->createToken('pos-token')->plainTextToken;

        $user->forceFill(['is_active' => false])->save();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertForbidden();
    }

    public function test_deactivated_user_is_forbidden_on_permissioned_endpoint(): void
    {
        $user = $this->superAdmin(['is_active' => false]);

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/settings')->assertForbidden();
    }
}
