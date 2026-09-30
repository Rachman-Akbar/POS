<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\ItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Draft adalah order yang disimpan sementara: ia ada di database dengan status
 * draft karena belum diproses, tapi tidak membentuk invoice, tidak menarik
 * stok, tidak mem-post jurnal, dan tidak masuk dapur. Draft baru menjadi
 * order sungguhan saat kasir melanjutkannya dari tab Pesanan.
 */
class DraftFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $kitchen;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ChartOfAccountSeeder::class, PaymentMethodSeeder::class, ProductSeeder::class]);

        $this->cashier = $this->staff($this->cashierPermissions(), ['role' => 'cashier']);
        $this->kitchen = $this->staff($this->kitchenPermissions(), ['role' => 'kitchen']);
        $this->product = Product::firstOrFail();
    }

    private function saveDraft(int $qty = 2): Order
    {
        $response = $this->actingAs($this->cashier)->postJson('/api/orders/draft', [
            'table_number' => '5',
            'items' => [['product_id' => $this->product->id, 'qty' => $qty]],
        ]);

        $response->assertCreated();

        return Order::firstOrFail();
    }

    public function test_saving_a_draft_creates_an_unprocessed_order(): void
    {
        $order = $this->saveDraft();

        $this->assertSame(OrderStatus::Draft->value, $order->status);
        $this->assertSame(PaymentStatus::Unpaid->value, $order->payment_status);
        $this->assertSame('5', $order->table_number);
        $this->assertCount(1, $order->items);
    }

    public function test_draft_does_not_create_an_invoice(): void
    {
        $this->saveDraft();

        $this->assertDatabaseCount('sales_invoices', 0);
        $this->assertDatabaseCount('sales_receipts', 0);
    }

    public function test_draft_does_not_reserve_stock(): void
    {
        $stockBefore = $this->product->stock;

        $this->saveDraft(3);

        $this->assertSame($stockBefore, $this->product->fresh()->stock);
    }

    public function test_draft_does_not_post_journal_entries(): void
    {
        $this->saveDraft();

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_draft_does_not_reach_the_kitchen(): void
    {
        $this->saveDraft();

        $this->actingAs($this->staff($this->kitchenPermissions()))
            ->getJson('/api/kitchen/items')
            ->assertOk()
            ->assertJsonCount(0, 'data.waiting')
            ->assertJsonCount(0, 'data.cooking')
            ->assertJsonCount(0, 'data.sent')
            ->assertJsonCount(0, 'data.done');
    }

    public function test_draft_is_hidden_from_the_waiter_active_queue(): void
    {
        $this->saveDraft();

        $this->actingAs($this->staff($this->waiterPermissions()))
            ->getJson('/api/orders?active_only=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_draft_is_hidden_from_unpaid_orders(): void
    {
        $this->saveDraft();

        $this->actingAs($this->cashier)
            ->getJson('/api/payments/unpaid-orders')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_draft_is_listed_for_the_cashier_transactions(): void
    {
        $draft = $this->saveDraft();

        $this->actingAs($this->cashier)
            ->getJson('/api/orders/transactions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $draft->id)
            ->assertJsonPath('data.0.status', OrderStatus::Draft->value)
            ->assertJsonPath('data.0.invoice', null);
    }

    public function test_finalizing_a_draft_issues_the_invoice_and_sends_it_to_the_kitchen(): void
    {
        $draft = $this->saveDraft(2);
        $stockBefore = $this->product->stock;

        $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$draft->id}/finalize", [
                'payment_method' => 'cash',
                'paid_amount' => $draft->total_amount,
            ])
            ->assertOk();

        $draft->refresh();

        $this->assertSame(OrderStatus::Pending->value, $draft->status);
        $this->assertSame(PaymentStatus::Paid->value, $draft->payment_status);
        $this->assertNotNull($draft->invoice);
        $this->assertSame(InvoiceStatus::Paid->value, $draft->invoice->status);
        $this->assertSame($stockBefore - 2, $this->product->fresh()->stock);
        $this->assertDatabaseCount('journal_entries', 2); // invoice + receipt
    }

    public function test_finalized_draft_reaches_the_kitchen_as_dipesan(): void
    {
        $draft = $this->saveDraft();

        $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$draft->id}/finalize", [
                'payment_method' => 'cash',
                'paid_amount' => $draft->total_amount,
            ])
            ->assertOk();

        $this->actingAs($this->staff($this->kitchenPermissions()))
            ->getJson('/api/kitchen/items')
            ->assertOk()
            ->assertJsonCount(1, 'data.waiting');
    }

    public function test_finalized_draft_can_be_left_unpaid_for_later_settlement(): void
    {
        $draft = $this->saveDraft();

        $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$draft->id}/finalize", [])
            ->assertOk();

        $draft->refresh();

        $this->assertSame(OrderStatus::Pending->value, $draft->status);
        $this->assertSame(PaymentStatus::Unpaid->value, $draft->payment_status);
        $this->assertSame(InvoiceStatus::Issued->value, $draft->invoice->status);
        $this->assertDatabaseCount('sales_receipts', 0);
    }

    public function test_continuing_a_draft_does_not_take_any_payment(): void
    {
        $draft = $this->saveDraft();

        $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$draft->id}/finalize")
            ->assertOk();

        $draft->refresh();

        $this->assertSame(OrderStatus::Pending->value, $draft->status);
        $this->assertSame('0.00', $draft->paid_amount);
        $this->assertSame(PaymentStatus::Unpaid->value, $draft->payment_status);
        $this->assertDatabaseCount('sales_receipts', 0);
    }

    public function test_a_continued_draft_can_then_be_settled_from_the_cashier(): void
    {
        $draft = $this->saveDraft();

        $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$draft->id}/finalize")
            ->assertOk();

        $draft->refresh();
        $invoice = $draft->invoice;

        $this->actingAs($this->cashier)
            ->postJson("/api/payments/orders/{$draft->id}/settle", [
                'payment_method' => 'cash',
            ])
            ->assertOk();

        $this->assertDatabaseHas('sales_invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::Paid->value,
        ]);
    }

    public function test_a_draft_records_the_cashier_who_saved_it(): void
    {
        $draft = $this->saveDraft();

        $this->assertSame($this->cashier->id, $draft->user_id);
    }

    public function test_continuing_a_fully_paid_draft_marks_the_order_pay_now(): void
    {
        $draft = $this->saveDraft();

        $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$draft->id}/finalize", [
                'payment_method' => 'cash',
                'paid_amount' => $draft->total_amount,
            ])
            ->assertOk();

        $this->assertSame('pay_now', $draft->refresh()->payment_type);
    }

    public function test_continuing_a_draft_without_payment_stays_pay_later(): void
    {
        $draft = $this->saveDraft();

        $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$draft->id}/finalize")
            ->assertOk();

        $this->assertSame('pay_later', $draft->refresh()->payment_type);
    }

    public function test_a_draft_item_cannot_have_its_production_stage_changed(): void
    {
        $draft = $this->saveDraft();

        $this->actingAs($this->kitchen)
            ->patchJson("/api/kitchen/items/{$draft->items()->first()->id}/status", [
                'status' => ItemStatus::Cooking->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Item draft belum diproses dan tidak bisa diubah tahapnya.');
    }

    public function test_a_normal_order_cannot_be_finalized_as_draft(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/orders', [
            'payment_type' => 'pay_now',
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ])->assertCreated();

        $order = Order::firstOrFail();

        $this->actingAs($this->cashier)
            ->postJson("/api/orders/{$order->id}/finalize", [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pesanan ini bukan draft.');
    }

    public function test_draft_journal_is_empty_until_finalized(): void
    {
        $draft = $this->saveDraft();
        $this->assertSame(0, JournalEntry::count());

        $this->actingAs($this->cashier)->postJson("/api/orders/{$draft->id}/finalize", [
            'payment_method' => 'cash',
            'paid_amount' => $draft->total_amount,
        ])->assertOk();

        $this->assertGreaterThan(0, JournalEntry::count());
    }

    public function test_a_draft_can_be_deleted_cleanly(): void
    {
        $draft = $this->saveDraft();
        $itemId = $draft->items()->firstOrFail()->id;

        $this->actingAs($this->cashier)
            ->deleteJson("/api/orders/{$draft->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('orders', ['id' => $draft->id]);
        $this->assertDatabaseMissing('order_items', ['id' => $itemId]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'transaction',
            'action' => 'delete',
        ]);
    }

    public function test_a_normal_order_cannot_be_deleted(): void
    {
        $this->actingAs($this->cashier)->postJson('/api/orders', [
            'payment_type' => 'pay_now',
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ])->assertCreated();

        $order = Order::where('status', '!=', OrderStatus::Draft->value)->firstOrFail();

        $this->actingAs($this->cashier)
            ->deleteJson("/api/orders/{$order->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Pesanan ini bukan draft.');

        // Transaksi tetap utuh setelah percobaan hapus gagal.
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_deleting_a_draft_requires_the_update_permission(): void
    {
        $draft = $this->saveDraft();
        $reader = $this->staff(['transaction.view']);

        $this->actingAs($reader)
            ->deleteJson("/api/orders/{$draft->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $draft->id]);
    }
}
