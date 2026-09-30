<?php

namespace Tests\Feature;

use App\Models\CashBankAccount;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
        $this->actingAsSuperAdmin();
    }

    public function test_admin_can_read_default_cashier_flags(): void
    {
        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.cashier_show_favorites', true)
            ->assertJsonPath('data.cashier_show_stock', true)
            ->assertJsonPath('data.cashier_show_sku', true)
            ->assertJsonPath('data.cashier_enable_ppn', true)
            ->assertJsonPath('data.cashier_enable_prepay', false);
    }

    public function test_admin_can_update_cashier_flags(): void
    {
        $this->putJson('/api/admin/settings', [
            'flags' => [
                'cashier_show_stock' => false,
                'cashier_show_sku' => false,
                'cashier_enable_prepay' => true,
            ],
        ])->assertOk()
            ->assertJsonPath('data.cashier_show_stock', false)
            ->assertJsonPath('data.cashier_show_sku', false)
            ->assertJsonPath('data.cashier_enable_prepay', true);

        $this->assertSame('false', Setting::get('pos.cashier_show_stock'));
        $this->assertSame('false', Setting::get('pos.cashier_show_sku'));
        $this->assertSame('true', Setting::get('pos.cashier_enable_prepay'));
    }

    public function test_public_settings_expose_cashier_flags_and_tables(): void
    {
        $response = $this->getJson('/api/settings');

        $response->assertOk()
            ->assertJsonPath('data.cashier.cashier_show_favorites', true)
            ->assertJsonPath('data.cashier.cashier_show_sku', true)
            ->assertJsonIsArray('data.table_numbers');

        $tables = $response->json('data.table_numbers');
        $this->assertContains('1', $tables);
    }

    public function test_admin_settings_expose_default_appearance(): void
    {
        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('appearance.mode', 'light')
            ->assertJsonPath('appearance.accent', 'system');
    }

    public function test_public_settings_expose_appearance(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'app.theme_mode'],
            ['group' => 'app', 'value' => 'dark', 'is_active' => true]
        );

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.appearance.mode', 'dark')
            ->assertJsonPath('data.appearance.accent', 'system');
    }

    public function test_admin_can_update_appearance(): void
    {
        $this->putJson('/api/admin/settings', [
            'appearance' => ['mode' => 'system', 'accent' => 'emerald'],
        ])->assertOk()
            ->assertJsonPath('appearance.mode', 'system')
            ->assertJsonPath('appearance.accent', 'emerald');

        $this->assertSame('system', Setting::get('app.theme_mode'));
        $this->assertSame('emerald', Setting::get('app.theme_accent'));
    }

    public function test_appearance_rejects_unknown_mode_and_accent(): void
    {
        $this->putJson('/api/admin/settings', ['appearance' => ['mode' => 'neon']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('appearance.mode');

        $this->putJson('/api/admin/settings', ['appearance' => ['accent' => 'chartreuse']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('appearance.accent');
    }

    public function test_appearance_falls_back_to_defaults_when_stored_value_is_unknown(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'app.theme_mode'],
            ['group' => 'app', 'value' => 'neon', 'is_active' => true]
        );
        Setting::query()->updateOrCreate(
            ['key' => 'app.theme_accent'],
            ['group' => 'app', 'value' => 'chartreuse', 'is_active' => true]
        );

        $this->assertSame(['mode' => 'light', 'accent' => 'system'], Setting::appearance());
    }

    public function test_only_one_default_account_per_payment_method(): void
    {
        $method = PaymentMethod::create([
            'code' => 'bca',
            'type' => 'bank',
            'name' => 'Transfer BCA',
            'mdr_rate' => 0,
            'is_active' => true,
        ]);

        $first = CashBankAccount::factory()->create([
            'name' => 'BCA Utama',
            'type' => 'bank',
            'payment_method_id' => $method->id,
            'is_default' => true,
        ]);
        $second = CashBankAccount::factory()->create([
            'name' => 'BCA Cadangan',
            'type' => 'bank',
            'payment_method_id' => $method->id,
            'is_default' => false,
        ]);

        $this->putJson("/api/cash-bank-accounts/{$second->id}", [
            'name' => $second->name,
            'type' => $second->type,
            'payment_method_id' => $method->id,
            'is_default' => true,
        ])->assertOk();

        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);
    }

    public function test_admin_can_read_and_reorder_product_categories(): void
    {
        Product::factory()->create(['name' => 'Nasi Goreng', 'category' => 'Makanan']);
        Product::factory()->create(['name' => 'Es Teh', 'category' => 'Minuman']);
        Product::factory()->create(['name' => 'Kentang', 'category' => 'Snack']);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Makanan')
            ->assertJsonPath('data.0.total', 1)
            ->assertJsonPath('data.1.name', 'Minuman')
            ->assertJsonPath('data.2.name', 'Snack');

        $this->putJson('/api/categories/order', [
            'order' => ['Snack', 'Minuman', 'Makanan'],
        ])->assertOk()
            ->assertJsonPath('data.0.name', 'Snack')
            ->assertJsonPath('data.2.name', 'Makanan');

        $this->assertSame(['Snack', 'Minuman', 'Makanan'], Setting::categoryOrder());
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.category_order.0', 'Snack');
    }

    public function test_category_order_rejects_incomplete_or_unknown_names(): void
    {
        Product::factory()->create(['category' => 'Makanan']);
        Product::factory()->create(['category' => 'Minuman']);

        $this->putJson('/api/categories/order', ['order' => ['Makanan']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');

        $this->putJson('/api/categories/order', ['order' => ['Makanan', 'Minuman', 'Ghost']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');

        $this->putJson('/api/categories/order', ['order' => ['Makanan', 'Makanan']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order.1');

        $this->assertSame([], Setting::categoryOrder());
    }

    public function test_admin_can_create_bank_account(): void
    {
        $this->postJson('/api/cash-bank-accounts', [
            'name' => 'Bank BCA',
            'type' => 'bank',
            'account_number' => '1234567890',
            'bank_name' => 'BCA',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Bank BCA');

        $this->assertDatabaseHas('cash_bank_accounts', ['name' => 'Bank BCA']);
    }

    public function test_admin_can_update_bank_account(): void
    {
        $account = CashBankAccount::factory()->create(['name' => 'Kas Lama', 'type' => 'kas']);

        $this->putJson("/api/cash-bank-accounts/{$account->id}", [
            'name' => 'Kas Utama',
            'type' => 'kas',
        ])->assertOk();

        $this->assertSame('Kas Utama', $account->fresh()->name);
    }

    public function test_admin_can_delete_bank_account(): void
    {
        $account = CashBankAccount::factory()->create();

        $this->deleteJson("/api/cash-bank-accounts/{$account->id}")->assertNoContent();

        $this->assertDatabaseMissing('cash_bank_accounts', ['id' => $account->id]);
    }

    public function test_bank_account_requires_valid_type(): void
    {
        $this->postJson('/api/cash-bank-accounts', [
            'name' => 'Kartu Kredit',
            'type' => 'credit',
        ])->assertUnprocessable();
    }

    public function test_admin_can_toggle_product_favorite(): void
    {
        $product = Product::factory()->create(['is_favorite' => false]);

        $this->patchJson("/api/products/{$product->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true);

        $this->patchJson("/api/products/{$product->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);
    }

    public function test_admin_can_create_payment_method(): void
    {
        $this->postJson('/api/payment-methods', [
            'code' => 'bca',
            'type' => 'bank',
            'name' => 'Transfer BCA',
            'mdr_rate' => 0.003,
        ])->assertCreated()
            ->assertJsonPath('data.type', 'bank');

        $this->assertDatabaseHas('payment_methods', ['code' => 'bca', 'type' => 'bank']);
    }

    public function test_admin_can_update_and_delete_payment_method(): void
    {
        $method = PaymentMethod::create([
            'code' => 'mandiri',
            'type' => 'bank',
            'name' => 'Mandiri',
            'mdr_rate' => 0.002,
            'is_active' => true,
        ]);

        $this->putJson("/api/payment-methods/{$method->id}", [
            'code' => 'mandiri',
            'type' => 'bank',
            'name' => 'Bank Mandiri',
            'mdr_rate' => 0.0025,
        ])->assertOk()->assertJsonPath('data.name', 'Bank Mandiri');

        $this->deleteJson("/api/payment-methods/{$method->id}")->assertNoContent();

        $this->assertDatabaseMissing('payment_methods', ['id' => $method->id]);
    }

    public function test_payment_method_requires_valid_type(): void
    {
        $this->postJson('/api/payment-methods', [
            'code' => 'credit',
            'type' => 'credit',
            'name' => 'Kartu Kredit',
            'mdr_rate' => 0.02,
        ])->assertUnprocessable();
    }
}
