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
    }

    public function test_admin_can_read_default_cashier_flags(): void
    {
        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.cashier_show_favorites', true)
            ->assertJsonPath('data.cashier_show_stock', true)
            ->assertJsonPath('data.cashier_enable_ppn', true)
            ->assertJsonPath('data.cashier_enable_prepay', false);
    }

    public function test_admin_can_update_cashier_flags(): void
    {
        $this->putJson('/api/admin/settings', [
            'flags' => [
                'cashier_show_stock' => false,
                'cashier_enable_prepay' => true,
            ],
        ])->assertOk()
            ->assertJsonPath('data.cashier_show_stock', false)
            ->assertJsonPath('data.cashier_enable_prepay', true);

        $this->assertSame('false', Setting::get('pos.cashier_show_stock'));
        $this->assertSame('true', Setting::get('pos.cashier_enable_prepay'));
    }

    public function test_public_settings_expose_cashier_flags_and_tables(): void
    {
        $response = $this->getJson('/api/settings');

        $response->assertOk()
            ->assertJsonPath('data.cashier.cashier_show_favorites', true)
            ->assertJsonIsArray('data.table_numbers');

        $tables = $response->json('data.table_numbers');
        $this->assertContains('1', $tables);
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
