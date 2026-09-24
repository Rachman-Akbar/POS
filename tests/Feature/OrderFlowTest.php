<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\JournalDetail;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $waiter;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ChartOfAccountSeeder::class, PaymentMethodSeeder::class, ProductSeeder::class]);

        $this->waiter = User::factory()->create(['role' => 'waiter']);
        $this->product = Product::firstOrFail();
    }

    public function test_waiter_can_create_a_pay_now_order(): void
    {
        $initialStock = (float) $this->product->stock;

        $response = $this->actingAs($this->waiter)->postJson('/api/orders', [
            'table_number' => 'Meja 1',
            'payment_type' => PaymentType::PayNow->value,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 2],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('data.payment_status', PaymentStatus::Paid->value);

        $this->assertDatabaseHas('orders', [
            'payment_type' => PaymentType::PayNow->value,
            'status' => OrderStatus::Pending->value,
        ]);

        // Stock was reduced.
        $this->product->refresh();
        $this->assertSame($initialStock - 2, (float) $this->product->stock);

        // Invoice issued and paid.
        $order = Order::firstOrFail();
        $this->assertSame(1, $order->invoice()->count());
        $this->assertSame(1, $order->invoice->receipts()->count());
    }

    public function test_waiter_can_create_a_pay_later_order(): void
    {
        $response = $this->actingAs($this->waiter)->postJson('/api/orders', [
            'table_number' => 'Meja 2',
            'payment_type' => PaymentType::PayLater->value,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('data.payment_status', PaymentStatus::Unpaid->value);

        $order = Order::firstOrFail();
        $this->assertSame(0, $order->invoice->receipts()->count());
    }

    public function test_partial_pay_now_requires_prepay_enabled(): void
    {
        $response = $this->actingAs($this->waiter)->postJson('/api/orders', [
            'payment_type' => PaymentType::PayNow->value,
            'paid_amount' => 1000,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_partial_pay_now_records_partial_payment_when_prepay_enabled(): void
    {
        Setting::query()->create([
            'key' => 'pos.cashier_enable_prepay',
            'group' => 'pos',
            'value' => 'true',
            'is_active' => true,
        ]);

        $partial = round((float) $this->product->price / 2, 2);

        $response = $this->actingAs($this->waiter)->postJson('/api/orders', [
            'payment_type' => PaymentType::PayNow->value,
            'paid_amount' => $partial,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('data.payment_status', PaymentStatus::Partial->value);

        $order = Order::firstOrFail();
        $this->assertSame($partial, (float) $order->paid_amount);
        $this->assertSame(1, $order->invoice->receipts()->count());
        $this->assertSame(InvoiceStatus::Issued->value, $order->invoice->status);
    }

    public function test_stock_is_not_negative_when_qty_exceeds_stock(): void
    {
        $this->product->update(['stock' => 1]);

        $response = $this->actingAs($this->waiter)->postJson('/api/orders', [
            'payment_type' => PaymentType::PayNow->value,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 5],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invoice_journal_is_balanced(): void
    {
        $this->actingAs($this->waiter)->postJson('/api/orders', [
            'table_number' => 'Meja 1',
            'payment_type' => PaymentType::PayNow->value,
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
        ]);

        $invoice = SalesInvoice::firstOrFail();
        $trialBalance = $this->trialBalanceFor('sales_invoice');

        $expectedCogs = (float) $this->product->cost_price;

        $this->assertSame($this->netDebit($trialBalance), $this->netCredit($trialBalance));
        $this->assertSame(round($invoice->total_amount + $expectedCogs, 2), $this->netDebit($trialBalance));
    }

    /**
     * @param  array<int, JournalDetail>  $details
     */
    private function netDebit(array $details): float
    {
        return round((float) collect($details)->sum('debit'), 2);
    }

    /**
     * @param  array<int, JournalDetail>  $details
     */
    private function netCredit(array $details): float
    {
        return round((float) collect($details)->sum('credit'), 2);
    }

    /**
     * @return array<int, JournalDetail>
     */
    private function trialBalanceFor(string $type): array
    {
        $entry = JournalEntry::where('type', $type)->firstOrFail();

        return $entry->details->all();
    }
}
