<?php

namespace Tests\Feature;

use App\Enums\CustomerType;
use App\Enums\PaymentType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierCustomerTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ChartOfAccountSeeder::class, PaymentMethodSeeder::class, ProductSeeder::class]);

        $this->cashier = User::factory()->create(['role' => 'cashier']);
        $this->product = Product::firstOrFail();
    }

    public function test_order_can_be_created_without_a_customer(): void
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/orders', [
            'payment_type' => PaymentType::PayNow->value,
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ]);

        $response->assertCreated()->assertJsonPath('data.customer_id', null);
        $this->assertDatabaseHas('orders', ['customer_id' => null]);
    }

    public function test_order_can_be_attached_to_an_individual_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->cashier)->postJson('/api/orders', [
            'payment_type' => PaymentType::PayNow->value,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.customer.id', $customer->id);

        $this->assertDatabaseHas('orders', ['customer_id' => $customer->id]);
    }

    public function test_order_can_be_attached_to_a_business_customer(): void
    {
        $customer = Customer::factory()->business()->create();

        $response = $this->actingAs($this->cashier)->postJson('/api/orders', [
            'payment_type' => PaymentType::PayNow->value,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer.customer_type', CustomerType::Business->value);
    }

    public function test_order_rejects_an_inactive_customer(): void
    {
        $customer = Customer::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->cashier)->postJson('/api/orders', [
            'payment_type' => PaymentType::PayNow->value,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('customer_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_rejects_an_unknown_customer(): void
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/orders', [
            'payment_type' => PaymentType::PayNow->value,
            'customer_id' => 999999,
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('customer_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cashier_search_returns_only_active_customers(): void
    {
        Customer::factory()->create(['name' => 'Budi Aktif']);
        Customer::factory()->create(['name' => 'Budi Nonaktif', 'is_active' => false]);

        $response = $this->actingAs($this->cashier)->getJson('/api/customers?search=Budi');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Budi Aktif', $response->json('data.0.name'));
    }

    public function test_cashier_search_matches_company_name_and_phone(): void
    {
        Customer::factory()->business()->create(['company_name' => 'PT Nusantara Jaya', 'phone' => '081234567890']);
        Customer::factory()->create(['name' => 'Orang Lain']);

        $this->actingAs($this->cashier)
            ->getJson('/api/customers?search=Nusantara')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->cashier)
            ->getJson('/api/customers?search=0812345')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_cashier_can_register_an_individual_customer_and_use_it_immediately(): void
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/customers', [
            'customer_type' => CustomerType::Individual->value,
            'name' => 'Kasir Baru',
            'email' => 'Kasir.Baru@Example.com',
            'phone' => '0812 3456 7890',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Kasir Baru');

        // Email dinormalisasi menjadi huruf kecil agar tetap unik.
        $this->assertDatabaseHas('customers', ['email' => 'kasir.baru@example.com']);

        $customerId = $response->json('data.id');

        $this->actingAs($this->cashier)->postJson('/api/orders', [
            'payment_type' => PaymentType::PayNow->value,
            'customer_id' => $customerId,
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ])->assertCreated()->assertJsonPath('data.customer_id', $customerId);
    }

    public function test_cashier_can_register_a_business_customer(): void
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/customers', [
            'customer_type' => CustomerType::Business->value,
            'company_name' => 'PT Nusantara Jaya Sentosa',
            'nik' => '3171014503825482',
            'npwp' => '01.005.429.4.300.185',
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Selatan',
            'postal_code' => '12190',
            'country' => 'Indonesia',
            'name' => 'Siti Aminah',
            'email' => 'siti.aminah@example.com',
            'phone' => '081234567891',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer_type', CustomerType::Business->value)
            ->assertJsonPath('data.company_name', 'PT Nusantara Jaya Sentosa');
    }

    public function test_business_customer_requires_company_fields(): void
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/customers', [
            'customer_type' => CustomerType::Business->value,
            'name' => 'Tanpa Company',
            'email' => 'tanpa.company@example.com',
            'phone' => '081234567892',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_name', 'nik', 'npwp', 'province', 'city', 'postal_code', 'country']);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_creation_rejects_invalid_nik_and_duplicate_email(): void
    {
        Customer::factory()->create(['email' => 'kembar@example.com']);

        $this->actingAs($this->cashier)->postJson('/api/customers', [
            'customer_type' => CustomerType::Individual->value,
            'name' => 'NIK Pendek',
            'email' => 'nik.pendek@example.com',
            'phone' => '081234567893',
        ])->assertCreated();

        $this->actingAs($this->cashier)->postJson('/api/customers', [
            'customer_type' => CustomerType::Business->value,
            'company_name' => 'PT NIK Salah',
            'nik' => '12345',
            'npwp' => '01.234.567.8-901.000',
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
            'postal_code' => '40115',
            'country' => 'Indonesia',
            'name' => 'NIK Pendek',
            'email' => 'kembar@example.com',
            'phone' => '081234567894',
        ])->assertStatus(422)->assertJsonValidationErrors(['nik', 'email']);
    }

    public function test_invoice_still_issued_when_a_customer_is_attached(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->cashier)->postJson('/api/orders', [
            'payment_type' => PaymentType::PayNow->value,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ])->assertCreated();

        $order = Order::firstOrFail();
        $invoice = $order->invoice;

        $this->assertNotNull($invoice);
        $this->assertSame((float) $order->total_amount, (float) $invoice->total_amount);
        $this->assertSame(1, $invoice->receipts()->count());
    }
}
