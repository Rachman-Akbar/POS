<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $kitchen;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ChartOfAccountSeeder::class, PaymentMethodSeeder::class, ProductSeeder::class]);

        $this->kitchen = User::factory()->create(['role' => 'kitchen']);
        $this->product = Product::firstOrFail();
    }

    private function createOrder(?User $user = null): Order
    {
        $user ??= User::factory()->create(['role' => 'waiter']);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'payment_type' => 'pay_later',
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ]);

        $response->assertCreated();

        return Order::firstOrFail();
    }

    public function test_kitchen_queue_contains_pending_items(): void
    {
        $order = $this->createOrder();

        $response = $this->actingAs($this->kitchen)->getJson('/api/kitchen/items');

        $response->assertOk()
            ->assertJsonCount(1, 'data.waiting')
            ->assertJsonCount(0, 'data.cooking')
            ->assertJsonCount(0, 'data.sent')
            ->assertJsonCount(0, 'data.done')
            ->assertJsonPath('data.waiting.0.order.id', $order->id);
    }

    public function test_kitchen_can_move_item_through_production_steps(): void
    {
        $order = $this->createOrder();
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => ItemStatus::Cooking->value])
            ->assertOk()
            ->assertJsonPath('data.status', ItemStatus::Cooking->value);

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => ItemStatus::Sent->value])
            ->assertOk()
            ->assertJsonPath('data.status', ItemStatus::Sent->value);

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => ItemStatus::Done->value])
            ->assertOk()
            ->assertJsonPath('data.status', ItemStatus::Done->value);

        $this->assertSame(ItemStatus::Done->value, $item->refresh()->status);
    }

    public function test_kitchen_cannot_skip_or_regress_item_status(): void
    {
        $order = $this->createOrder();
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => ItemStatus::Done->value])
            ->assertStatus(422);

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => ItemStatus::Cooking->value])
            ->assertOk();

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => ItemStatus::Done->value])
            ->assertStatus(422);

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => ItemStatus::Sent->value])
            ->assertOk();

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => ItemStatus::Cooking->value])
            ->assertStatus(422);
    }

    public function test_completed_orders_are_hidden_from_kitchen_queue(): void
    {
        $order = $this->createOrder();
        $item = $order->items()->firstOrFail();
        $waiter = User::where('role', 'waiter')->firstOrFail();

        foreach ([ItemStatus::Cooking, ItemStatus::Sent, ItemStatus::Done] as $status) {
            $this->actingAs($this->kitchen)
                ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => $status->value])
                ->assertOk();
        }

        $this->actingAs($waiter)->postJson("/api/orders/{$order->id}/complete");

        $this->actingAs($this->kitchen)->getJson('/api/kitchen/items')
            ->assertOk()
            ->assertJsonCount(0, 'data.waiting')
            ->assertJsonCount(0, 'data.cooking')
            ->assertJsonCount(0, 'data.sent')
            ->assertJsonCount(0, 'data.done');
    }
}
