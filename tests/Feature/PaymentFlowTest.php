<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\PaymentMethod as PaymentMethodModel;
use App\Models\Product;
use App\Models\SalesReceipt;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
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

    private function createPayLaterOrder(): Order
    {
        $waiter = User::factory()->create(['role' => 'waiter']);

        $this->actingAs($waiter)->postJson('/api/orders', [
            'payment_type' => 'pay_later',
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ]);

        return Order::firstOrFail();
    }

    public function test_cashier_sees_pending_invoices(): void
    {
        $this->createPayLaterOrder();

        $this->actingAs($this->cashier)->getJson('/api/payments/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_cashier_settles_pay_later_invoice(): void
    {
        $order = $this->createPayLaterOrder();

        $response = $this->actingAs($this->cashier)->postJson("/api/payments/orders/{$order->id}/settle", [
            'payment_method' => 'qris',
        ]);

        $response->assertOk();

        $receipt = SalesReceipt::firstOrFail();
        $qris = PaymentMethodModel::where('code', 'qris')->firstOrFail();
        $expectedMdr = round((float) $order->total_amount * (float) $qris->mdr_rate, 2);

        $this->assertSame($expectedMdr, (float) $receipt->mdr_fee);
        $this->assertSame(PaymentStatus::Paid->value, $order->refresh()->payment_status);
        $this->assertSame(InvoiceStatus::Paid->value, $order->invoice->refresh()->status);
    }

    public function test_cashier_cannot_settle_an_already_paid_order(): void
    {
        $order = $this->createPayLaterOrder();

        $this->actingAs($this->cashier)->postJson("/api/payments/orders/{$order->id}/settle", [
            'payment_method' => 'cash',
        ]);

        $this->actingAs($this->cashier)->postJson("/api/payments/orders/{$order->id}/settle", [
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertDatabaseCount('sales_receipts', 1);
    }

    public function test_cashier_can_settle_partial_amount_then_remaining(): void
    {
        $order = $this->createPayLaterOrder();
        $half = round((float) $order->total_amount / 2, 2);

        $this->actingAs($this->cashier)->postJson("/api/payments/orders/{$order->id}/settle", [
            'payment_method' => 'cash',
            'amount' => $half,
        ])->assertOk();

        $this->assertSame(PaymentStatus::Partial->value, $order->refresh()->payment_status);
        $this->assertSame(InvoiceStatus::Issued->value, $order->invoice->refresh()->status);

        $this->actingAs($this->cashier)->postJson("/api/payments/orders/{$order->id}/settle", [
            'payment_method' => 'cash',
        ])->assertOk();

        $this->assertSame(PaymentStatus::Paid->value, $order->refresh()->payment_status);
        $this->assertSame(InvoiceStatus::Paid->value, $order->invoice->refresh()->status);
        $this->assertSame(2, $order->invoice->receipts()->count());
    }

    public function test_settlement_journal_is_balanced(): void
    {
        $order = $this->createPayLaterOrder();

        $this->actingAs($this->cashier)->postJson("/api/payments/orders/{$order->id}/settle", [
            'payment_method' => 'bank',
        ]);

        $entry = JournalEntry::where('type', 'sales_receipt')->firstOrFail();
        $debit = (float) $entry->details->sum('debit');
        $credit = (float) $entry->details->sum('credit');

        $this->assertSame(round($debit, 2), round($credit, 2));
        $this->assertSame(round((float) $order->total_amount, 2), round($credit, 2));
    }
}
