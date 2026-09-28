<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_product_with_variants(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();
        Sanctum::actingAs($admin);

        $create = $this->post('/api/admin/products', [
            'name' => 'Kaos Internal',
            'slug' => '',
            'category_id' => $category->id,
            'type' => 'product',
            'description' => 'Kaos operasional.',
            'brand' => 'Kisha',
            'status' => 'published',
            'is_featured' => '1',
            'is_active' => '1',
            'existing_images' => json_encode([]),
            'variant_mode' => '1',
            'variants' => json_encode([
                [
                    'name' => 'Merah / M',
                    'sku' => 'KAOS-MERAH-M',
                    'price' => 85000,
                    'attributes' => [
                        ['name' => 'Warna', 'value' => 'Merah'],
                        ['name' => 'Ukuran', 'value' => 'M'],
                    ],
                    'track_stock' => true,
                    'stock' => 10,
                    'is_default' => true,
                    'is_active' => true,
                ],
            ]),
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.slug', 'kaos-internal')
            ->assertJsonPath('data.variants.0.sku', 'KAOS-MERAH-M');

        $product = Product::query()->firstOrFail();
        $variant = $product->variants()->firstOrFail();

        $update = $this->post("/api/admin/products/{$product->id}", [
            'name' => 'Kaos Internal Baru',
            'slug' => '',
            'category_id' => $category->id,
            'type' => 'product',
            'description' => 'Deskripsi baru.',
            'brand' => 'Kisha',
            'status' => 'published',
            'is_featured' => '0',
            'is_active' => '1',
            'existing_images' => json_encode([]),
            'variant_mode' => '1',
            'variants' => json_encode([
                [
                    'id' => $variant->id,
                    'name' => 'Merah / M',
                    'sku' => 'KAOS-MERAH-M-NEW',
                    'price' => 90000,
                    'attributes' => [['name' => 'Warna', 'value' => 'Merah']],
                    'track_stock' => true,
                    'stock' => 8,
                    'is_default' => true,
                    'is_active' => true,
                ],
            ]),
        ]);

        $update
            ->assertOk()
            ->assertJsonPath('data.name', 'Kaos Internal Baru')
            ->assertJsonPath('data.variants.0.price', 90000);

        $this->deleteJson("/api/admin/products/{$product->id}")->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
