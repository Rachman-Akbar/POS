<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\ItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\JournalDetail;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReceipt;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class TestingSeeder extends Seeder
{
    private const COUNT = 50;

    /**
     * Seed 50 rows across every domain table for manual testing.
     */
    public function run(): void
    {
        $this->cleanup();

        $users = $this->seedUsers();
        $products = $this->seedProducts();
        $this->seedPaymentMethods();
        $this->seedCashBankAccounts();
        $accounts = $this->seedChartOfAccounts();
        $this->seedSettings();
        $this->seedOrders($users, $products, $accounts);
    }

    /**
     * Remove previously generated test rows so the seeder can be re-run.
     */
    private function cleanup(): void
    {
        JournalEntry::query()->where('number', 'like', 'JE-TEST-%')->delete();
        Order::query()->where('order_number', 'like', 'ORD-TEST-%')->delete();
        Product::query()->where('name', 'like', 'Test Produk %')->delete();
        PaymentMethod::query()->where('code', 'like', 'test_pm_%')->delete();
        CashBankAccount::query()->where('name', 'like', 'Test Akun Kas %')->delete();
        ChartOfAccount::query()->where('name', 'like', 'Test Akun COA %')->delete();
        Setting::query()->where('key', 'like', 'testing.%')->delete();
        User::query()->where('email', 'like', 'test.user%@pos.test')->delete();
    }

    /**
     * @return Collection<int, User>
     */
    private function seedUsers(): Collection
    {
        $roles = ['admin', 'waiter', 'kitchen', 'cashier'];

        return collect(range(1, self::COUNT))->map(fn (int $i) => User::create([
            'name' => "Test User {$i}",
            'email' => "test.user{$i}@pos.test",
            'password' => 'password',
            'role' => $roles[$i % count($roles)],
        ]));
    }

    /**
     * @return Collection<int, Product>
     */
    private function seedProducts(): Collection
    {
        $categories = ['Makanan', 'Minuman', 'Snack', 'Dessert'];

        return collect(range(1, self::COUNT))->map(fn (int $i) => Product::create([
            'name' => "Test Produk {$i}",
            'description' => "Deskripsi produk uji {$i}",
            'price' => 8000 + ($i * 750),
            'cost_price' => 4000 + ($i * 300),
            'stock' => 20 + ($i * 3),
            'category' => $categories[$i % count($categories)],
            'image' => null,
            'is_favorite' => $i % 7 === 0,
            'is_active' => true,
        ]));
    }

    private function seedPaymentMethods(): void
    {
        $types = ['kas', 'bank', 'qris'];

        foreach (range(1, self::COUNT) as $i) {
            PaymentMethod::create([
                'code' => "test_pm_{$i}",
                'type' => $types[$i % count($types)],
                'name' => "Test Metode {$i}",
                'mdr_rate' => 0,
                'is_active' => true,
            ]);
        }
    }

    private function seedCashBankAccounts(): void
    {
        foreach (range(1, self::COUNT) as $i) {
            $isBank = $i % 2 === 0;

            CashBankAccount::create([
                'name' => "Test Akun Kas {$i}",
                'type' => $isBank ? 'bank' : 'kas',
                'account_number' => $isBank ? '8800'.str_pad((string) $i, 6, '0', STR_PAD_LEFT) : null,
                'bank_name' => $isBank ? 'Bank Uji' : null,
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return Collection<string, ChartOfAccount>
     */
    private function seedChartOfAccounts(): Collection
    {
        $types = [
            ['type' => 'asset', 'normal_balance' => 'debit'],
            ['type' => 'liability', 'normal_balance' => 'credit'],
            ['type' => 'revenue', 'normal_balance' => 'credit'],
            ['type' => 'expense', 'normal_balance' => 'debit'],
        ];

        $accounts = collect();

        foreach (range(1, self::COUNT) as $i) {
            $meta = $types[$i % count($types)];

            $account = ChartOfAccount::create([
                'code' => '9'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'name' => "Test Akun COA {$i}",
                'type' => $meta['type'],
                'normal_balance' => $meta['normal_balance'],
                'is_active' => true,
            ]);

            $accounts->put($account->code, $account);
        }

        return $accounts;
    }

    private function seedSettings(): void
    {
        foreach (range(1, self::COUNT) as $i) {
            Setting::create([
                'key' => "testing.flag_{$i}",
                'group' => 'testing',
                'value' => $i % 2 === 0 ? 'true' : 'false',
                'label' => "Test Flag {$i}",
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, Product>  $products
     * @param  Collection<string, ChartOfAccount>  $accounts
     */
    private function seedOrders(Collection $users, Collection $products, Collection $accounts): void
    {
        $methods = ['cash', 'bank', 'qris'];

        foreach (range(1, self::COUNT) as $i) {
            $date = now()->subDays($i % 30)->subMinutes($i * 7);

            $lineCount = 1 + ($i % 3);
            $subtotal = 0.0;
            $lines = [];

            for ($line = 0; $line < $lineCount; $line++) {
                $product = $products[($i + $line) % $products->count()];
                $qty = 1 + (($i + $line) % 2);
                $lines[] = ['product' => $product, 'qty' => $qty];
                $subtotal += $qty * (float) $product->price;
            }

            $discount = $i % 4 === 0 ? round($subtotal * 0.1, 2) : 0.0;
            $tax = round(($subtotal - $discount) * 0.11, 2);
            $total = round($subtotal - $discount + $tax, 2);

            $mod = $i % 5;
            $status = match (true) {
                $mod === 0 => PaymentStatus::Unpaid,
                $mod === 1, $mod === 2 => PaymentStatus::Partial,
                default => PaymentStatus::Paid,
            };

            $order = Order::create([
                'order_number' => 'ORD-TEST-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'table_number' => $i % 4 === 0 ? null : (string) (($i % 12) + 1),
                'user_id' => $users[($i - 1) % $users->count()]->id,
                'payment_type' => $i % 3 === 0 ? PaymentType::PayLater->value : PaymentType::PayNow->value,
                'status' => $i % 2 === 0 ? OrderStatus::Completed->value : OrderStatus::Pending->value,
                'payment_status' => $status->value,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'paid_amount' => 0,
                'notes' => null,
            ]);

            $itemStatus = $order->status === OrderStatus::Completed->value
                ? ItemStatus::Done
                : ItemStatus::Pending;

            foreach ($lines as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'qty' => $line['qty'],
                    'price' => $line['product']->price,
                    'status' => $itemStatus->value,
                    'notes' => null,
                ]);
            }

            $invoice = SalesInvoice::create([
                'order_id' => $order->id,
                'invoice_number' => 'INV-TEST-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'total_amount' => $total,
                'status' => $status === PaymentStatus::Paid ? InvoiceStatus::Paid->value : InvoiceStatus::Issued->value,
                'issued_at' => $date,
            ]);

            $paidAmount = $this->seedReceipts($invoice, $status, $total, $methods[$i % count($methods)], $date);

            $order->update([
                'paid_amount' => $paidAmount,
                'payment_status' => match (true) {
                    $paidAmount <= 0 => PaymentStatus::Unpaid->value,
                    $paidAmount >= $total => PaymentStatus::Paid->value,
                    default => PaymentStatus::Partial->value,
                },
            ]);

            $this->seedJournal($order, $invoice, $accounts, $total, $date);

            $order->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();
            $invoice->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();
        }
    }

    /**
     * @param  array<int, string>  $methods
     */
    private function seedReceipts(SalesInvoice $invoice, PaymentStatus $status, float $total, string $method, \DateTimeInterface $date): float
    {
        if ($status === PaymentStatus::Unpaid) {
            return 0.0;
        }

        $portions = $status === PaymentStatus::Paid
            ? [$total]
            : [round($total * 0.3, 2), round($total * 0.3, 2)];

        $paid = 0.0;

        foreach ($portions as $index => $gross) {
            SalesReceipt::create([
                'invoice_id' => $invoice->id,
                'payment_method' => $method,
                'gross_amount' => $gross,
                'mdr_fee' => 0,
                'net_amount' => $gross,
                'payment_date' => $date,
            ]);

            $paid += $gross;
        }

        return round($paid, 2);
    }

    /**
     * @param  Collection<string, ChartOfAccount>  $accounts
     */
    private function seedJournal(Order $order, SalesInvoice $invoice, Collection $accounts, float $total, \DateTimeInterface $date): void
    {
        $entry = JournalEntry::create([
            'number' => 'JE-TEST-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT),
            'type' => 'sales',
            'date' => $date,
            'description' => "Penjualan {$order->order_number}",
            'reference_type' => SalesInvoice::class,
            'reference_id' => $invoice->id,
        ]);

        $debitAccount = $accounts->firstWhere('type', 'asset');
        $creditAccount = $accounts->firstWhere('type', 'revenue');

        JournalDetail::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $debitAccount->id,
            'debit' => $total,
            'credit' => 0,
            'description' => 'Kas / Piutang',
        ]);

        JournalDetail::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $creditAccount->id,
            'debit' => 0,
            'credit' => $total,
            'description' => 'Pendapatan Penjualan',
        ]);
    }
}
