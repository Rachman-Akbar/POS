<?php

namespace Tests\Feature;

use App\Models\CashBankAccount;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SalesReceipt;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiAccountPaymentTest extends TestCase
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

    private function createBankAccounts(string $code, array $names): array
    {
        $method = PaymentMethod::query()->updateOrCreate(
            ['code' => $code],
            ['type' => 'bank', 'name' => strtoupper($code), 'mdr_rate' => 0.0015, 'is_active' => true],
        );

        return collect($names)
            ->map(fn (string $name, int $index) => CashBankAccount::factory()->create([
                'name' => $name,
                'type' => 'bank',
                'bank_name' => strtoupper($code),
                'account_number' => '10000'.$index,
                'payment_method_id' => $method->id,
                'is_default' => $index === 0,
                'is_active' => true,
            ]))
            ->all();
    }

    public function test_cashier_can_choose_among_multiple_accounts_of_one_bank_method(): void
    {
        [$accountA, $accountB, $accountC] = $this->createBankAccounts('bca', ['Rekening A', 'Rekening B', 'Rekening C']);

        $this->actingAs($this->cashier)->postJson('/api/orders', [
            'payment_type' => 'pay_now',
            'payment_method' => 'bca',
            'payment_account_id' => $accountC->id,
            'paid_amount' => 100000,
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ])->assertCreated();

        $receipt = SalesReceipt::firstOrFail();

        $this->assertSame($accountC->id, $receipt->payment_account_id);
        $this->assertNotSame($accountA->id, $receipt->payment_account_id);
        $this->assertNotSame($accountB->id, $receipt->payment_account_id);
    }

    public function test_order_falls_back_to_the_default_account_when_none_is_selected(): void
    {
        [$accountA] = $this->createBankAccounts('bca', ['Rekening A', 'Rekening B']);

        $this->actingAs($this->cashier)->postJson('/api/orders', [
            'payment_type' => 'pay_now',
            'payment_method' => 'bca',
            'paid_amount' => 100000,
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ])->assertCreated();

        $this->assertSame($accountA->id, SalesReceipt::firstOrFail()->payment_account_id);
    }

    public function test_order_rejects_an_account_that_belongs_to_another_method(): void
    {
        $this->createBankAccounts('bca', ['Rekening A', 'Rekening B']);
        [$bniAccount] = $this->createBankAccounts('bni', ['Rekening BNI']);

        $this->actingAs($this->cashier)->postJson('/api/orders', [
            'payment_type' => 'pay_now',
            'payment_method' => 'bca',
            'payment_account_id' => $bniAccount->id,
            'paid_amount' => 100000,
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('payment_account_id');

        $this->assertDatabaseCount('sales_receipts', 0);
    }

    public function test_order_rejects_an_inactive_account(): void
    {
        [$accountA] = $this->createBankAccounts('bca', ['Rekening A', 'Rekening B']);
        $accountA->update(['is_active' => false]);

        $this->actingAs($this->cashier)->postJson('/api/orders', [
            'payment_type' => 'pay_now',
            'payment_method' => 'bca',
            'payment_account_id' => $accountA->id,
            'paid_amount' => 100000,
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('payment_account_id');
    }

    public function test_settings_expose_every_account_of_each_payment_method(): void
    {
        $this->createBankAccounts('bca', ['Rekening A', 'Rekening B', 'Rekening C']);

        $response = $this->actingAs($this->cashier)->getJson('/api/settings')->assertOk();

        $accounts = collect($response->json('data.payment_methods'))
            ->firstWhere('code', 'bca')['accounts'];

        $this->assertCount(3, $accounts);
        $this->assertSame('Rekening A', $accounts[0]['name']);
        $this->assertSame('100000', $accounts[0]['account_number']);
    }

    public function test_cashier_can_settle_a_draft_into_the_chosen_account(): void
    {
        [$accountA, $accountB] = $this->createBankAccounts('bca', ['Rekening A', 'Rekening B']);

        $waiter = User::factory()->create(['role' => 'waiter']);
        $this->actingAs($waiter)->postJson('/api/orders', [
            'payment_type' => 'pay_later',
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ])->assertCreated();

        $order = Order::firstOrFail();

        $this->actingAs($this->cashier)->postJson("/api/payments/orders/{$order->id}/settle", [
            'payment_method' => 'bca',
            'payment_account_id' => $accountB->id,
        ])->assertOk();

        $this->assertSame($accountB->id, SalesReceipt::firstOrFail()->payment_account_id);
        $this->assertNotSame($accountA->id, SalesReceipt::firstOrFail()->payment_account_id);
    }

    public function test_settling_rejects_an_account_from_another_method(): void
    {
        $this->createBankAccounts('bca', ['Rekening A', 'Rekening B']);
        [$bniAccount] = $this->createBankAccounts('bni', ['Rekening BNI']);

        $waiter = User::factory()->create(['role' => 'waiter']);
        $this->actingAs($waiter)->postJson('/api/orders', [
            'payment_type' => 'pay_later',
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        ])->assertCreated();

        $order = Order::firstOrFail();

        $this->actingAs($this->cashier)->postJson("/api/payments/orders/{$order->id}/settle", [
            'payment_method' => 'bca',
            'payment_account_id' => $bniAccount->id,
        ])->assertStatus(422)->assertJsonValidationErrors('payment_account_id');

        $this->assertDatabaseCount('sales_receipts', 0);
    }
}
