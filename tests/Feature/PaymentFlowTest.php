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

    /**
     * Tabel "Pesanan" menampilkan seluruh transaksi, bukan hanya faktur
     * gantung: yang sudah lunas tetap ikut agar terlihat riwayatnya.
     */
    public function test_cashier_sees_every_invoice_not_only_unpaid_ones(): void
    {
        $this->createPayLaterOrder();
        $unsettled = $this->createPayLaterOrder();

        $this->actingAs($this->cashier)->postJson("/api/payments/orders/{$unsettled->id}/settle", [
            'payment_method' => 'qris',
        ])->assertOk();

        $this->actingAs($this->cashier)->getJson('/api/payments/invoices')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->cashier)->getJson('/api/payments/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_invoice_list_carries_amounts_and_process_state_for_the_table(): void
    {
        $order = $this->createPayLaterOrder();

        $response = $this->actingAs($this->cashier)->getJson('/api/payments/invoices');

        $response->assertOk()
            ->assertJsonPath('data.0.invoice_number', $order->invoice->invoice_number)
            ->assertJsonPath('data.0.total_amount', number_format((float) $order->total_amount, 2, '.', ''))
            ->assertJsonPath('data.0.receipts', [])
            ->assertJsonPath('data.0.order.order_number', $order->order_number)
            ->assertJsonPath('data.0.order.items.0.status', 'pending');
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

    public function test_settling_more_than_remaining_records_only_the_balance_and_returns_change(): void
    {
        $order = $this->createPayLaterOrder();
        $total = (float) $order->total_amount;
        $tendered = round($total + 20_000, 2);

        $response = $this->actingAs($this->cashier)->postJson("/api/payments/orders/{$order->id}/settle", [
            'payment_method' => 'cash',
            'amount' => $tendered,
        ]);

        $response->assertOk();

        $this->assertSame(20_000.0, (float) $response->json('data.change'));
        $this->assertSame($total, (float) $response->json('data.applied'));

        // Only the balance is recorded: overpayment never inflates revenue.
        $this->assertSame(1, $order->invoice->receipts()->count());
        $this->assertSame($total, (float) $order->invoice->receipts()->firstOrFail()->gross_amount);
        $this->assertSame($total, (float) $order->refresh()->paid_amount);
        $this->assertSame(PaymentStatus::Paid->value, $order->payment_status);

        $entry = JournalEntry::where('type', 'sales_receipt')->firstOrFail();
        $this->assertSame(
            round((float) $entry->details->sum('debit'), 2),
            round((float) $entry->details->sum('credit'), 2),
        );
        $this->assertSame($total, (float) $entry->details->sum('credit'));
    }

    public function test_settling_with_change_still_records_the_settled_amount_in_the_journal(): void
    {
        $order = $this->createPayLaterOrder();
        $total = (float) $order->total_amount;

        $this->actingAs($this->cashier)->postJson("/api/payments/orders/{$order->id}/settle", [
            'payment_method' => 'cash',
            'amount' => round($total * 2, 2),
        ])->assertOk();

        $this->assertSame($total, (float) $order->invoice->receipts()->sum('gross_amount'));

        $entry = JournalEntry::where('type', 'sales_receipt')->firstOrFail();
        $this->assertSame($total, (float) $entry->details->sum('debit'));
        $this->assertSame($total, (float) $entry->details->sum('credit'));
    }
}
