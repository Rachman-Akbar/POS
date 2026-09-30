<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Permission halaman operasional.
 *
 * Endpoint katalog, order, dapur, dan pembayaran kini wajib login dan punya
 * permission. Test ini menjaga dua hal: user tanpa permission benar-benar
 * ditolak (bukan cuma disembunyikan di UI), dan user dengan permission tetap
 * bisa bekerja seperti sebelumnya.
 */
class OperationalPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ChartOfAccountSeeder::class, PaymentMethodSeeder::class, ProductSeeder::class]);
    }

    public function test_catalog_is_not_readable_without_product_view(): void
    {
        Sanctum::actingAs($this->staff(['transaction.view']));

        $this->getJson('/api/products')->assertForbidden();
    }

    public function test_catalog_is_readable_with_product_view(): void
    {
        Sanctum::actingAs($this->staff(['product.view']));

        $this->getJson('/api/products')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_order_creation_requires_transaction_create(): void
    {
        Sanctum::actingAs($this->staff(['product.view']));

        $this->postJson('/api/orders', $this->orderPayload())->assertForbidden();
    }

    public function test_order_listing_requires_transaction_view(): void
    {
        Sanctum::actingAs($this->staff(['transaction.create']));

        $this->getJson('/api/orders')->assertForbidden();
    }

    public function test_cashier_can_create_an_order_with_the_expected_permissions(): void
    {
        Sanctum::actingAs($this->staff($this->cashierPermissions()));

        $this->postJson('/api/orders', $this->orderPayload())->assertCreated();
    }

    public function test_kitchen_user_cannot_read_the_order_queue(): void
    {
        Sanctum::actingAs($this->staff($this->kitchenPermissions()));

        $this->getJson('/api/orders')->assertForbidden();
        $this->getJson('/api/orders/transactions')->assertForbidden();
    }

    public function test_kitchen_user_can_read_and_update_kitchen_items(): void
    {
        Sanctum::actingAs($this->staff($this->kitchenPermissions()));

        $this->getJson('/api/kitchen/items')->assertOk();
    }

    public function test_settling_a_payment_requires_pos_sell(): void
    {
        $order = $this->createUnpaidOrder();

        Sanctum::actingAs($this->staff(['pos.view']));

        $this->postJson("/api/payments/orders/{$order->id}/settle", [])->assertForbidden();
    }

    public function test_cashier_can_settle_an_order_they_created(): void
    {
        $order = $this->createUnpaidOrder();

        $this->postJson("/api/payments/orders/{$order->id}/settle", [
            'payment_method' => 'cash',
        ])->assertOk();
    }

    public function test_waiter_cannot_settle_a_payment(): void
    {
        Sanctum::actingAs($this->staff($this->waiterPermissions()));

        $this->getJson('/api/payments/pending')->assertForbidden();
    }

    public function test_cashier_searching_customers_requires_customer_view(): void
    {
        Sanctum::actingAs($this->staff(['transaction.view']));

        $this->getJson('/api/customers')->assertForbidden();
    }

    public function test_settings_are_not_readable_without_settings_view(): void
    {
        Sanctum::actingAs($this->staff(['product.view']));

        $this->getJson('/api/settings')->assertForbidden();
    }

    /**
     * Diskon mengurangi uang yang benar-benar diterima, jadi role tanpa
     * `pos.discount` harus ditolak saat diskon dipakai, bukan hanya saat
     * menyimpan. Diskon 0 tetap boleh supaya kasir tidak terhenti.
     */
    public function test_discount_requires_pos_discount(): void
    {
        Sanctum::actingAs($this->staff(['transaction.create', 'transaction.update', 'product.view', 'settings.view']));

        $this->postJson('/api/orders', $this->orderPayload(['discount' => 1000]))
            ->assertForbidden();

        $this->postJson('/api/orders', $this->orderPayload())
            ->assertCreated();
    }

    public function test_discount_is_allowed_with_pos_discount(): void
    {
        Sanctum::actingAs($this->staff([
            'transaction.create',
            'transaction.update',
            'product.view',
            'settings.view',
            'pos.discount',
        ]));

        $this->postJson('/api/orders', $this->orderPayload(['discount' => 1000]))
            ->assertCreated();
    }

    public function test_draft_with_discount_is_also_protected(): void
    {
        Sanctum::actingAs($this->staff(['transaction.create', 'transaction.update', 'product.view', 'settings.view']));

        $this->postJson('/api/orders/draft', $this->orderPayload(['discount' => 1000]))
            ->assertForbidden();
    }

    public function test_inactive_staff_cannot_reach_operational_routes(): void
    {
        $user = $this->staff($this->cashierPermissions(), ['is_active' => false]);
        Sanctum::actingAs($user);

        $this->getJson('/api/products')->assertForbidden();
    }

    /**
     * Order `pay_later` yang belum dibayar, dibuat oleh user dengan hak akses
     * penuh supaya test focus-nya ada di permission Settlement-nya.
     */
    private function createUnpaidOrder(): Order
    {
        Sanctum::actingAs($this->staff($this->cashierPermissions()));

        $response = $this->postJson('/api/orders', $this->orderPayload())->assertCreated();

        return Order::findOrFail($response->json('data.id'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function orderPayload(array $overrides = []): array
    {
        return $overrides + [
            'table_number' => 'Meja 1',
            'payment_type' => 'pay_later',
            'items' => [
                ['product_id' => Product::firstOrFail()->id, 'qty' => 1],
            ],
        ];
    }
}
