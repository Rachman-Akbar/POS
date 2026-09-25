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
    private const ORDER_COUNT = 60;

    /**
     * Additional realistic payment channels shown alongside the core
     * cash / bank / qris methods.
     *
     * @var array<string, array{type: string, name: string, mdr_rate: float}>
     */
    private const PAYMENT_CHANNELS = [
        'bca' => ['type' => 'bank', 'name' => 'BCA', 'mdr_rate' => 0.0015],
        'bni' => ['type' => 'bank', 'name' => 'BNI', 'mdr_rate' => 0.0015],
        'bri' => ['type' => 'bank', 'name' => 'BRI', 'mdr_rate' => 0.0015],
        'mandiri' => ['type' => 'bank', 'name' => 'Bank Mandiri', 'mdr_rate' => 0.0015],
        'bsi' => ['type' => 'bank', 'name' => 'Bank Syariah Indonesia', 'mdr_rate' => 0.0015],
        'gopay' => ['type' => 'ewallet', 'name' => 'GoPay', 'mdr_rate' => 0.002],
        'ovo' => ['type' => 'ewallet', 'name' => 'OVO', 'mdr_rate' => 0.002],
        'dana' => ['type' => 'ewallet', 'name' => 'DANA', 'mdr_rate' => 0.002],
        'shopeepay' => ['type' => 'ewallet', 'name' => 'ShopeePay', 'mdr_rate' => 0.002],
    ];

    /**
     * Cash & bank accounts linked to the payment methods above.
     *
     * @var array<int, array{name: string, type: string, method: string, is_default: bool, account_number: string|null, bank_name: string|null}>
     */
    private const BANK_ACCOUNTS = [
        ['name' => 'Kas Utama', 'type' => 'kas', 'method' => 'cash', 'is_default' => true, 'account_number' => null, 'bank_name' => null],
        ['name' => 'Kas Kecil', 'type' => 'kas', 'method' => 'cash', 'is_default' => false, 'account_number' => null, 'bank_name' => null],
        ['name' => 'Bank BCA – Operasional', 'type' => 'bank', 'method' => 'bank', 'is_default' => true, 'account_number' => '1234567890', 'bank_name' => 'BCA'],
        ['name' => 'BCA – Rekening Utama', 'type' => 'bank', 'method' => 'bca', 'is_default' => true, 'account_number' => '8831223456', 'bank_name' => 'BCA'],
        ['name' => 'BNI – Rekening Bisnis', 'type' => 'bank', 'method' => 'bni', 'is_default' => true, 'account_number' => '4567891234', 'bank_name' => 'BNI'],
        ['name' => 'BRI – Rekening Penerimaan', 'type' => 'bank', 'method' => 'bri', 'is_default' => true, 'account_number' => '000011223344', 'bank_name' => 'BRI'],
        ['name' => 'Mandiri – Rekening Kas', 'type' => 'bank', 'method' => 'mandiri', 'is_default' => true, 'account_number' => '1230007890', 'bank_name' => 'Bank Mandiri'],
        ['name' => 'BSI – Tabungan Usaha', 'type' => 'bank', 'method' => 'bsi', 'is_default' => true, 'account_number' => '7001234567', 'bank_name' => 'BSI'],
        ['name' => 'GoPay – Saldo Bisnis', 'type' => 'bank', 'method' => 'gopay', 'is_default' => true, 'account_number' => '081280001234', 'bank_name' => 'GoPay'],
        ['name' => 'OVO – Saldo Bisnis', 'type' => 'bank', 'method' => 'ovo', 'is_default' => true, 'account_number' => '081280005678', 'bank_name' => 'OVO'],
        ['name' => 'DANA – Saldo Bisnis', 'type' => 'bank', 'method' => 'dana', 'is_default' => true, 'account_number' => '081280009999', 'bank_name' => 'DANA'],
        ['name' => 'ShopeePay – Saldo Bisnis', 'type' => 'bank', 'method' => 'shopeepay', 'is_default' => true, 'account_number' => '081280001111', 'bank_name' => 'ShopeePay'],
        ['name' => 'QRIS – Penerimaan', 'type' => 'bank', 'method' => 'qris', 'is_default' => true, 'account_number' => '8831223456', 'bank_name' => 'QRIS / BCA'],
    ];

    /** @var array<string, int> */
    private array $dailySequences = [];

    /**
     * Seed realistic demo data (staff, payment channels, accounts, and orders)
     * so every POS screen looks and behaves like production.
     */
    public function run(): void
    {
        $this->cleanup();

        $users = $this->seedUsers();
        $methods = $this->seedPaymentMethods();
        $accounts = $this->seedCashBankAccounts($methods);
        $products = Product::query()->where('is_active', true)->get();
        $this->seedOrders($users, $products, $methods, $accounts);
    }

    /**
     * Remove previously generated demo rows so the seeder can be re-run.
     */
    private function cleanup(): void
    {
        $staffIds = User::query()->where('email', 'like', '%@resto.id')->pluck('id');
        $orderIds = $staffIds->isNotEmpty() ? Order::query()->whereIn('user_id', $staffIds)->pluck('id') : collect();
        $invoiceIds = $orderIds->isNotEmpty() ? SalesInvoice::query()->whereIn('order_id', $orderIds)->pluck('id') : collect();

        if ($invoiceIds->isNotEmpty()) {
            JournalEntry::query()
                ->where('reference_type', SalesInvoice::class)
                ->whereIn('reference_id', $invoiceIds)
                ->delete();
        }

        if ($orderIds->isNotEmpty()) {
            Order::query()->whereIn('id', $orderIds)->delete();
        }

        JournalEntry::query()->where('number', 'like', 'JE-TEST-%')->delete();
        Order::query()->where('order_number', 'like', 'ORD-TEST-%')->delete();
        Product::query()->where('name', 'like', 'Test Produk %')->delete();
        PaymentMethod::query()->where('code', 'like', 'test_pm_%')->delete();
        CashBankAccount::query()->where('name', 'like', 'Test Akun Kas %')->delete();
        ChartOfAccount::query()->where('name', 'like', 'Test Akun COA %')->delete();
        Setting::query()->where('key', 'like', 'testing.%')->delete();
        User::query()->where('email', 'like', 'test.user%@pos.test')->delete();
        User::query()->where('email', 'like', '%@resto.id')->delete();
        PaymentMethod::query()->whereIn('code', array_keys(self::PAYMENT_CHANNELS))->delete();
        CashBankAccount::query()->whereIn('name', array_column(self::BANK_ACCOUNTS, 'name'))->delete();
    }

    /**
     * @return Collection<int, User>
     */
    private function seedUsers(): Collection
    {
        $staff = [
            ['Budi Santoso', 'admin', 'budi@resto.id'],
            ['Sari Wulandari', 'cashier', 'sari@resto.id'],
            ['Dewi Lestari', 'cashier', 'dewi@resto.id'],
            ['Ratna Sari', 'cashier', 'ratna@resto.id'],
            ['Dimas Prasetyo', 'kitchen', 'dimas@resto.id'],
            ['Agus Salim', 'kitchen', 'agus@resto.id'],
            ['Eko Nugroho', 'kitchen', 'eko@resto.id'],
            ['Siti Rahayu', 'waiter', 'siti@resto.id'],
            ['Rudi Hartono', 'waiter', 'rudi@resto.id'],
            ['Maya Anggraini', 'waiter', 'maya@resto.id'],
            ['Yoga Pratama', 'waiter', 'yoga@resto.id'],
            ['Nabila Putri', 'waiter', 'nabila@resto.id'],
        ];

        return collect($staff)->map(fn (array $person) => User::query()->updateOrCreate(
            ['email' => $person[2]],
            ['name' => $person[0], 'role' => $person[1], 'password' => 'password']
        ));
    }

    /**
     * @return Collection<string, PaymentMethod>
     */
    private function seedPaymentMethods(): Collection
    {
        $methods = PaymentMethod::query()->where('is_active', true)->get()->keyBy('code');

        foreach (self::PAYMENT_CHANNELS as $code => $data) {
            $methods->put($code, PaymentMethod::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'mdr_rate' => $data['mdr_rate'],
                    'is_active' => true,
                ]
            ));
        }

        return $methods;
    }

    /**
     * @param  Collection<string, PaymentMethod>  $methods
     * @return Collection<string, CashBankAccount>
     */
    private function seedCashBankAccounts(Collection $methods): Collection
    {
        $accounts = collect();

        foreach (self::BANK_ACCOUNTS as $data) {
            $account = CashBankAccount::query()->updateOrCreate(
                ['name' => $data['name']],
                [
                    'type' => $data['type'],
                    'payment_method_id' => $methods[$data['method']]->id,
                    'account_number' => $data['account_number'],
                    'bank_name' => $data['bank_name'],
                    'is_default' => $data['is_default'],
                    'is_active' => true,
                ]
            );

            if ($data['is_default']) {
                $accounts->put($data['method'], $account);
            }
        }

        return $accounts;
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, Product>  $products
     * @param  Collection<string, PaymentMethod>  $methods
     * @param  Collection<string, CashBankAccount>  $accounts
     */
    private function seedOrders(Collection $users, Collection $products, Collection $methods, Collection $accounts): void
    {
        $phases = ['pending', 'cooking', 'sent', 'mixture', 'done'];

        foreach (range(1, self::ORDER_COUNT) as $i) {
            $phase = $phases[($i - 1) % count($phases)];
            $user = $users[($i - 1) % $users->count()];
            $created = $this->createdAt($phase, $i);

            $lineCount = 1 + ($i % 3);
            $lines = [];
            $subtotal = 0.0;

            for ($line = 0; $line < $lineCount; $line++) {
                $product = $products[($i + $line) % $products->count()];
                $qty = 1 + (($i + $line) % 3);
                $lines[] = ['product' => $product, 'qty' => $qty, 'notes' => $this->lineNotes(($i + $line) % 5)];
                $subtotal += $qty * (float) $product->price;
            }

            $discount = $i % 4 === 0 ? round($subtotal * 0.1, 2) : 0.0;
            $tax = round(($subtotal - $discount) * 0.11, 2);
            $total = round($subtotal - $discount + $tax, 2);

            $isDone = $phase === 'done';
            $payLater = ! $isDone && ($i % 7 === 3 || $i % 9 === 5);
            $paymentType = $payLater ? PaymentType::PayLater : PaymentType::PayNow;
            $method = $isDone
                ? ['cash', 'bank', 'qris'][($i - 1) % 3]
                : ['cash', 'cash', 'qris', 'bank'][($i - 1) % 4];

            $order = Order::create([
                'order_number' => $this->orderNumber($created),
                'table_number' => $i % 6 === 0 ? null : 'Meja '.((($i - 1) % 12) + 1),
                'user_id' => $user->id,
                'payment_type' => $paymentType->value,
                'status' => $isDone ? OrderStatus::Completed->value : OrderStatus::Pending->value,
                'payment_status' => $payLater ? PaymentStatus::Unpaid->value : PaymentStatus::Paid->value,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'paid_amount' => 0,
                'notes' => $this->orderNotes(($i - 1) % 6),
            ]);

            $itemStatuses = $this->itemStatuses($phase, $lineCount);

            foreach ($lines as $index => $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'qty' => $line['qty'],
                    'price' => $line['product']->price,
                    'status' => $itemStatuses[$index]->value,
                    'notes' => $line['notes'],
                ]);
            }

            $invoice = SalesInvoice::create([
                'order_id' => $order->id,
                'invoice_number' => $this->invoiceNumber($created, $order->id),
                'total_amount' => $total,
                'status' => InvoiceStatus::Issued->value,
                'issued_at' => $created,
            ]);

            $paidAmount = $this->seedReceipts($invoice, $methods, $accounts, $total, $method, $created, $payLater, $i);

            $order->forceFill([
                'paid_amount' => $paidAmount,
                'payment_status' => match (true) {
                    $paidAmount <= 0 => PaymentStatus::Unpaid->value,
                    $paidAmount >= $total => PaymentStatus::Paid->value,
                    default => PaymentStatus::Partial->value,
                },
            ])->saveQuietly();

            if ($paidAmount >= $total) {
                $invoice->forceFill(['status' => InvoiceStatus::Paid->value])->saveQuietly();
            }

            $this->seedJournal($order, $invoice, $subtotal - $discount, $tax, $method, $created);

            $order->forceFill(['created_at' => $created, 'updated_at' => $created])->saveQuietly();
            $invoice->forceFill(['created_at' => $created, 'updated_at' => $created])->saveQuietly();
        }
    }

    private function createdAt(string $phase, int $i): \DateTimeInterface
    {
        return match ($phase) {
            'pending' => now()->subMinutes(3 + ($i % 12)),
            'cooking' => now()->subMinutes(18 + ($i % 18)),
            'sent' => now()->subMinutes(40 + ($i % 25)),
            'mixture' => now()->subMinutes(70 + ($i % 45)),
            'done' => now()->subDays(1 + ($i % 7))->setTime(7 + ($i % 11), ($i * 7) % 60),
        };
    }

    private function orderNumber(\DateTimeInterface $created): string
    {
        $dateKey = $created->format('Ymd');

        if (! array_key_exists($dateKey, $this->dailySequences)) {
            $maxSeq = Order::query()
                ->where('order_number', 'like', "ORD-{$dateKey}-%")
                ->selectRaw('MAX(CAST(SUBSTRING_INDEX(order_number, "-", -1) AS UNSIGNED)) as seq')
                ->value('seq');

            $this->dailySequences[$dateKey] = (int) $maxSeq;
        }

        $this->dailySequences[$dateKey]++;

        return "ORD-{$dateKey}-".str_pad((string) $this->dailySequences[$dateKey], 4, '0', STR_PAD_LEFT);
    }

    private function invoiceNumber(\DateTimeInterface $created, int $orderId): string
    {
        return 'INV-'.$created->format('Ymd').'-'.str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, ItemStatus>
     */
    private function itemStatuses(string $phase, int $lineCount): array
    {
        return match ($phase) {
            'pending' => array_fill(0, $lineCount, ItemStatus::Pending),
            'cooking' => array_fill(0, $lineCount, ItemStatus::Cooking),
            'sent' => array_fill(0, $lineCount, ItemStatus::Sent),
            'done' => array_fill(0, $lineCount, ItemStatus::Done),
            'mixture' => $lineCount === 1
                ? [ItemStatus::Cooking]
                : array_merge([ItemStatus::Done], array_fill(1, $lineCount - 1, ItemStatus::Cooking)),
        };
    }

    private function lineNotes(int $n): ?string
    {
        return match ($n) {
            0 => null,
            1 => 'Tidak pedas',
            2 => 'Ekstra sambal',
            3 => 'Toping tambahan',
            default => 'Porsi besar',
        };
    }

    private function orderNotes(int $n): ?string
    {
        return match ($n) {
            0 => null,
            1 => 'Mohon tidak pakai MSG',
            2 => null,
            3 => 'Dimakan di tempat',
            4 => null,
            default => 'Pesanan dibungkus',
        };
    }

    /**
     * @param  Collection<string, PaymentMethod>  $methods
     * @param  Collection<string, CashBankAccount>  $accounts
     */
    private function seedReceipts(SalesInvoice $invoice, Collection $methods, Collection $accounts, float $total, string $method, \DateTimeInterface $date, bool $payLater, int $i): float
    {
        if ($payLater) {
            if ($i % 9 === 5) {
                $portions = [round($total * 0.3, 2), round($total * 0.3, 2)];
            } else {
                return 0.0;
            }
        } else {
            $portions = [$total];
        }

        $mdrRate = (float) $methods[$method]->mdr_rate;
        $accountId = $accounts[$method]->id ?? null;
        $paid = 0.0;

        foreach ($portions as $index => $gross) {
            $mdrFee = round($gross * $mdrRate, 2);

            SalesReceipt::create([
                'invoice_id' => $invoice->id,
                'payment_method' => $method,
                'payment_account_id' => $accountId,
                'gross_amount' => $gross,
                'mdr_fee' => $mdrFee,
                'net_amount' => round($gross - $mdrFee, 2),
                'payment_date' => $index > 0 ? $date->modify('+5 minutes') : $date,
            ]);

            $paid += $gross;
        }

        return round($paid, 2);
    }

    private function seedJournal(Order $order, SalesInvoice $invoice, float $revenue, float $tax, string $method, \DateTimeInterface $date): void
    {
        $total = round($revenue + $tax, 2);
        $assetCode = match ($method) {
            'bank' => '1310',
            'qris' => '1320',
            default => '1300',
        };

        $asset = ChartOfAccount::query()->where('code', $assetCode)->firstOrFail();
        $revenueAccount = ChartOfAccount::query()->where('code', '4000')->firstOrFail();
        $ppnAccount = ChartOfAccount::query()->where('code', '2500')->firstOrFail();

        $entry = JournalEntry::create([
            'number' => 'JRN-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            'type' => 'sales',
            'date' => $date,
            'description' => "Penjualan {$order->order_number}",
            'reference_type' => SalesInvoice::class,
            'reference_id' => $invoice->id,
        ]);

        JournalDetail::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $asset->id,
            'debit' => $total,
            'credit' => 0,
            'description' => 'Kas / Bank / QRIS',
        ]);

        JournalDetail::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => $revenue,
            'description' => 'Pendapatan Penjualan',
        ]);

        JournalDetail::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $ppnAccount->id,
            'debit' => 0,
            'credit' => $tax,
            'description' => 'PPN Keluaran',
        ]);
    }
}
