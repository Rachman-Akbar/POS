<?php

namespace Tests\Feature;

use App\Enums\CustomerType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsSuperAdmin();
    }

    public function test_admin_can_manage_master_categories(): void
    {
        Product::factory()->create(['name' => 'Nasi Goreng', 'category' => 'Makanan']);
        Product::factory()->create(['name' => 'Es Teh', 'category' => 'Minuman']);

        $this->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Makanan')
            ->assertJsonPath('data.0.total', 1)
            ->assertJsonPath('data.0.is_registered', false);

        $this->postJson('/api/admin/categories', ['name' => 'Makanan'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Makanan');

        $this->getJson('/api/admin/categories')
            ->assertJsonPath('data.0.is_registered', true)
            ->assertJsonPath('data.0.is_active', true);

        $this->postJson('/api/admin/categories', ['name' => 'Catering'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Catering');

        $this->assertDatabaseHas('categories', ['name' => 'Catering']);

        $category = Category::query()->where('name', 'Makanan')->firstOrFail();
        $this->assertNotNull($category);

        $this->putJson("/api/admin/categories/{$category->id}", [
            'name' => 'Hakanan Utama',
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.name', 'Hakanan Utama');

        $this->assertDatabaseHas('products', ['name' => 'Nasi Goreng', 'category' => 'Hakanan Utama']);

        $used = Category::query()->where('name', 'Hakanan Utama')->firstOrFail();
        $this->deleteJson("/api/admin/categories/{$used->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kategori masih dipakai 1 produk. Pindahkan produk terlebih dahulu.');

        $empty = Category::query()->where('name', 'Catering')->firstOrFail();
        $this->deleteJson("/api/admin/categories/{$empty->id}")->assertNoContent();
        $this->assertDatabaseMissing('categories', ['name' => 'Catering']);
    }

    public function test_admin_can_reorder_master_categories(): void
    {
        Product::factory()->create(['category' => 'Makanan']);
        Product::factory()->create(['category' => 'Minuman']);

        $this->postJson('/api/admin/categories', ['name' => 'Makanan']);
        $this->postJson('/api/admin/categories', ['name' => 'Minuman']);

        $this->putJson('/api/admin/categories/order', ['order' => ['Minuman', 'Makanan']])
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Minuman');

        $this->assertDatabaseHas('categories', ['name' => 'Minuman', 'sort_order' => 0]);

        $this->putJson('/api/admin/categories/order', ['order' => ['Minuman']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');
    }

    public function test_category_without_products_is_listed_and_status_is_kept(): void
    {
        $this->postJson('/api/admin/categories', ['name' => 'Catering'])->assertCreated();

        $this->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Catering')
            ->assertJsonPath('data.0.total', 0);

        $category = Category::query()->where('name', 'Catering')->firstOrFail();

        $this->putJson("/api/admin/categories/{$category->id}", ['name' => 'Catering', 'is_active' => false])->assertOk();

        $this->putJson("/api/admin/categories/{$category->id}", ['name' => 'Catering PREMIUM'])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('categories', ['name' => 'Catering PREMIUM', 'is_active' => false]);
    }

    public function test_product_category_is_registered_and_ordered_for_cashier(): void
    {
        $this->postJson('/api/admin/products', [
            'name' => 'Es Kelapa',
            'price' => 15000,
            'stock' => 10,
            'category' => 'Minuman',
        ])->assertCreated();

        $this->assertDatabaseHas('categories', ['name' => 'Minuman']);
        $this->assertSame(['Minuman'], Setting::categoryOrder());
    }

    public function test_admin_can_manage_master_products(): void
    {
        Product::factory()->create(['name' => 'Kentang Goreng', 'category' => 'Snack']);

        $this->getJson('/api/admin/products?search=Kentang')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Kentang Goreng');

        $this->postJson('/api/admin/products', [
            'name' => 'Es Kopi',
            'sku' => 'mnk-01',
            'price' => 18000,
            'cost_price' => 9000,
            'stock' => 20,
            'category' => 'Minuman',
        ])->assertCreated()->assertJsonPath('data.sku', 'MNK-01');

        $product = Product::query()->where('name', 'Es Kopi')->firstOrFail();

        $this->postJson('/api/admin/products', [
            'name' => 'Es Kopi Gula Aren',
            'sku' => 'mnk-01',
            'price' => 19000,
            'stock' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors('sku');

        $this->putJson("/api/admin/products/{$product->id}", [
            'name' => 'Es Kopi Susu',
            'sku' => 'mnk-02',
            'price' => 20000,
            'stock' => 8,
            'category' => 'Minuman',
            'is_favorite' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Es Kopi Susu')
            ->assertJsonPath('data.is_favorite', true);

        // Form admin selalu mengirim field wajib, tapi centang favorit/aktif tidak
        // terkirim saat dimatikan sehingga status lama harus tersimpan.
        $this->putJson("/api/admin/products/{$product->id}", [
            'name' => 'Es Kopi Susu',
            'sku' => 'mnk-02',
            'price' => 21000,
            'stock' => 8,
            'category' => 'Minuman',
        ])->assertOk()
            ->assertJsonPath('data.is_favorite', true)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 21000]);

        $this->deleteJson("/api/admin/products/{$product->id}")->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_product_with_order_items_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();
        $order = Order::create([
            'order_number' => 'INV-TEST-1',
            'payment_type' => 'pay_now',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_amount' => 1000,
        ]);
        $order->items()->create(['product_id' => $product->id, 'qty' => 1, 'price' => 1000]);

        $this->deleteJson("/api/admin/products/{$product->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Produk sudah dipakai pada transaksi. Nonaktifkan saja agar tidak tampil di kasir.');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_admin_can_manage_individual_customers(): void
    {
        $this->getJson('/api/admin/customers')->assertOk()->assertJsonPath('meta.total', 0);

        $this->postJson('/api/admin/customers', [
            'customer_type' => 'individual',
            'name' => 'Budi Santoso',
            'email' => 'Budi@Example.com',
            'phone' => '0812 3456 7890',
            'address' => 'Jl. Merdeka 1',
            'company_name' => 'PT Abadi',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'budi@example.com')
            ->assertJsonPath('data.address', 'Jl. Merdeka 1')
            ->assertJsonPath('data.company_name', null);

        $this->assertDatabaseHas('customers', [
            'name' => 'Budi Santoso',
            'customer_type' => 'individual',
            'nik' => null,
        ]);

        $customer = Customer::query()->where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame(CustomerType::Individual, $customer->customer_type);

        $this->postJson('/api/admin/customers', [
            'customer_type' => 'individual',
            'name' => 'Budi Duplikat',
            'email' => 'budi@example.com',
            'phone' => '0812 3456 7891',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->putJson("/api/admin/customers/{$customer->id}", [
            'customer_type' => 'individual',
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'phone' => '0812 9999 0000',
            'address' => 'Jl. Merdeka 1',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Budi Santoso')
            ->assertJsonPath('data.address', 'Jl. Merdeka 1')
            ->assertJsonPath('data.is_active', true);

        $this->getJson('/api/admin/customers?search=budi@example.com')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Budi Santoso')
            ->assertJsonPath('data.0.orders_count', 0);

        $this->deleteJson("/api/admin/customers/{$customer->id}")->assertNoContent();
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    /**
     * Data badan usaha boleh kosong. Aturan "wajib nama saja" berlaku
     * juga di menu admin, supaya kasir dan admin tidak punya syarat berbeda saat
     * menyimpan pelanggan yang sama.
     */
    public function test_business_customers_only_require_a_name(): void
    {
        $this->postJson('/api/admin/customers', [
            'customer_type' => 'business',
            'name' => 'Siti Aminah',
        ])->assertCreated()
            ->assertJsonPath('data.customer_type', 'business')
            ->assertJsonPath('data.company_name', null);

        $this->postJson('/api/admin/customers', [
            'customer_type' => 'business',
            'company_name' => 'PT Nusantara Jaya',
            'name' => 'Siti Aminah',
            'email' => 'siti@contoh.co.id',
            'phone' => '081298765432',
            'nik' => '3273010101900001',
            'npwp' => '01.234.567.8-901.000',
            'province' => 'Jawa Barat',
            'city' => 'Bandung',
            'postal_code' => '40115',
            'country' => 'Indonesia',
        ])->assertCreated()->assertJsonPath('data.company_name', 'PT Nusantara Jaya');

        $this->assertDatabaseHas('customers', [
            'email' => 'siti@contoh.co.id',
            'customer_type' => 'business',
            'city' => 'Bandung',
        ]);
    }
}
