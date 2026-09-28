<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_checkout_track_edit_and_cancel_order(): void
    {
        $product = Product::factory()->create();
        $variant = $product->variants()->firstOrFail();

        $checkout = $this->postJson('/api/checkout', [
            'name' => 'Budi Santoso',
            'division' => 'Finance',
            'phone' => '081234567890',
            'address' => 'Kantor Pusat',
            'notes' => null,
            'payment_method' => 'internal_billing',
            'items' => [[
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 2,
            ]],
        ]);

        $checkout
            ->assertCreated()
            ->assertJsonPath('data.total_amount', 250000)
            ->assertJsonPath('data.status', 'pending');

        $orderNumber = $checkout->json('data.order_number');

        $this->getJson('/api/orders/track?'.http_build_query([
            'order_number' => $orderNumber,
            'phone' => '081234567890',
        ]))->assertOk();

        $this->putJson("/api/orders/track/{$orderNumber}", [
            'phone_verification' => '081234567890',
            'name' => 'Budi Update',
            'division' => 'Accounting',
            'phone' => '081234567891',
            'address' => 'Kantor Cabang',
            'notes' => 'Catatan baru',
        ])->assertOk()->assertJsonPath('data.guest_name', 'Budi Update');

        $this->postJson("/api/orders/{$orderNumber}/cancel", [
            'phone' => '081234567891',
            'cancel_reason' => 'Tidak jadi dibutuhkan',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 5,
        ]);
    }
}
