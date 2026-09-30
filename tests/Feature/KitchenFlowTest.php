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

        $this->kitchen = $this->staff($this->kitchenPermissions(), ['role' => 'kitchen']);
        $this->product = Product::firstOrFail();
    }

    private function createOrder(?User $user = null): Order
    {
        $user ??= $this->staff($this->waiterPermissions(), ['role' => 'waiter']);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'payment_type' => 'pay_later',
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ]);

        $response->assertCreated();

        return Order::firstOrFail();
    }

    private function createDraft(): Order
    {
        $cashier = $this->staff($this->cashierPermissions(), ['role' => 'cashier']);

        $response = $this->actingAs($cashier)->postJson('/api/orders/draft', [
            'table_number' => '3',
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ]);

        $response->assertCreated();

        return Order::firstOrFail();
    }

    /**
     * Alur tahap item menu: Draft → Diproses → Dimasak → Dikirim → Selesai.
     *
     * Tahap `draft` dipakai item milik pesanan yang masih draft. Item itu
     * belum pernah masuk dapur dan tidak boleh tampil di papan; saat draft
     * diselesaikan, finalizeDraft() menaikkannya ke `pending` (Diproses).
     *
     * `cancelled` bukan tahap produksi: itu penanda yang dibuat admin saat
     * membatalkan transaksi, supaya dapur berhenti mengerjakan barang yang
     * sudah dibatalkan.
     */
    public function test_item_status_exposes_five_production_stages(): void
    {
        $this->assertSame(
            ['draft', 'pending', 'cooking', 'sent', 'done', 'cancelled'],
            array_column(ItemStatus::cases(), 'value'),
        );

        // Hanya lima tahap pertama yang punya tahap berikutnya.
        $this->assertSame(
            ['pending', 'cooking', 'sent', 'done', null, null],
            array_map(fn (ItemStatus $case): ?string => $case->next()?->value, ItemStatus::cases()),
        );

        $this->assertSame(
            ['Draft', 'Diproses', 'Dimasak', 'Dikirim', 'Selesai', 'Dibatalkan'],
            array_map(fn (ItemStatus $status) => $status->label(), ItemStatus::cases()),
        );
    }

    public function test_production_stages_advance_in_order(): void
    {
        $this->assertSame(ItemStatus::Pending, ItemStatus::Draft->next());
        $this->assertSame(ItemStatus::Cooking, ItemStatus::Pending->next());
        $this->assertSame(ItemStatus::Sent, ItemStatus::Cooking->next());
        $this->assertSame(ItemStatus::Done, ItemStatus::Sent->next());
        $this->assertNull(ItemStatus::Done->next());
    }

    public function test_draft_items_never_reach_the_kitchen_board(): void
    {
        $draft = $this->createDraft();

        $this->assertSame(
            ItemStatus::Draft->value,
            $draft->items()->firstOrFail()->status,
            'Item pesanan draft harus berstatus draft, bukan pending.',
        );

        $response = $this->actingAs($this->kitchen)->getJson('/api/kitchen/items');
        $response->assertOk()
            ->assertJsonCount(0, 'data.waiting')
            ->assertJsonCount(0, 'data.cooking')
            ->assertJsonCount(0, 'data.sent')
            ->assertJsonCount(0, 'data.done');
    }

    /**
     * Item draft tidak boleh bisa diubah tahapnya lewat papan dapur, karena
     * item itu belum pernah masuk produksi sama sekali. Tidak ada endpoint
     * yang bisa membuat draft item tampil di papan sebagai "menunggu".
     */
    public function test_kitchen_cannot_change_a_draft_item_status(): void
    {
        $draft = $this->createDraft();
        $item = $draft->items()->firstOrFail();

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => ItemStatus::Cooking->value])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Item draft belum diproses dan tidak bisa diubah tahapnya.');

        $this->assertSame(ItemStatus::Draft->value, $item->refresh()->status);
    }

    public function test_new_order_items_enter_the_kitchen_as_dipesan(): void
    {
        $order = $this->createOrder();

        $this->assertSame(
            ItemStatus::Pending->value,
            $order->items()->firstOrFail()->status,
        );
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

    public function test_kitchen_can_set_item_status_manually(): void
    {
        $order = $this->createOrder();
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => ItemStatus::Done->value])
            ->assertOk()
            ->assertJsonPath('data.status', ItemStatus::Done->value);

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => ItemStatus::Cooking->value])
            ->assertOk()
            ->assertJsonPath('data.status', ItemStatus::Cooking->value);

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => ItemStatus::Pending->value])
            ->assertOk()
            ->assertJsonPath('data.status', ItemStatus::Pending->value);

        $this->assertSame(ItemStatus::Pending->value, $item->refresh()->status);
    }

    public function test_kitchen_rejects_invalid_item_status(): void
    {
        $order = $this->createOrder();
        $item = $order->items()->firstOrFail();

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => 'served'])
            ->assertStatus(422);

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$item->id}/status", [])
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
