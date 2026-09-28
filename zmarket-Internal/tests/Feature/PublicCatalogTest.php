<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_browse_and_search_published_products(): void
    {
        Product::factory()->create(['name' => 'Laptop Internal']);
        Product::factory()->create(['status' => 'draft']);

        $this->getJson('/api/products?search=Laptop')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.default_variant.name', 'Default');
    }
}
