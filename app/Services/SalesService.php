<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\CashBankAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReceipt;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesService
{
    public function __construct(private readonly AccountingService $accountingService) {}

    /**
     * Create an order with its items, invoice, stock deduction and journal posting.
     *
     * @param  array<int, array{qty: int, product_id: int, notes?: string|null}>  $items
     * @param  array{discount?: float, tax_rate?: float|null, payment_method?: string|null, payment_account_id?: int|null, paid_amount?: float|null, notes?: string|null}  $options
     */
    public function createOrder(?User $user, string $tableNumber, PaymentType $paymentType, array $items, array $options = []): Order
    {
        return DB::transaction(function () use ($user, $tableNumber, $paymentType, $items, $options) {
            $order = Order::create([
                'order_number' => $this->nextOrderNumber(),
                'table_number' => $tableNumber ?: null,
                'user_id' => $user?->id,
                'payment_type' => $paymentType->value,
                'status' => OrderStatus::Pending->value,
                'payment_status' => PaymentStatus::Unpaid->value,
                'paid_amount' => 0,
                'notes' => $options['notes'] ?? null,
            ]);

            $subtotal = 0.0;
            $cogs = 0.0;

            foreach ($items as $line) {
                $product = Product::where('is_active', true)->findOrFail($line['product_id']);
                $qty = max(1, (int) $line['qty']);

                $this->decrementStock($product, $qty);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'price' => $product->price,
                    'notes' => $line['notes'] ?? null,
                ]);

                $subtotal += $qty * $product->price;
                $cogs += $qty * $product->cost_price;
            }

            $discount = min(max((float) ($options['discount'] ?? 0), 0), $subtotal);
            $taxableBase = $subtotal - $discount;

            $ppnRate = $options['tax_rate'] ?? Setting::get('pos.ppn_rate', 0);

            $taxAmount = round($taxableBase * ((float) $ppnRate / 100), 2);
            $totalAmount = round($taxableBase + $taxAmount, 2);

            $order->update([
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
            ]);

            $invoice = $this->issueInvoice($order, $cogs);

            if ($paymentType === PaymentType::PayNow) {
                $tendered = isset($options['paid_amount']) && $options['paid_amount'] !== null
                    ? (float) $options['paid_amount']
                    : $totalAmount;

                if ($tendered < $totalAmount && ! $this->prepayEnabled()) {
                    throw new \DomainException('Nominal pembayaran kurang dari total tagihan.');
                }

                $applied = round(min(max($tendered, 0), $totalAmount), 2);

                if ($applied > 0) {
                    $method = PaymentMethod::where('code', $options['payment_method'] ?? 'cash')->where('is_active', true)->first()
                        ?? PaymentMethod::where('code', 'cash')->firstOrFail();
                    $account = $this->resolvePaymentAccount($method, $options['payment_account_id'] ?? null);
                    $this->processPayment($invoice, $method, $applied, $account);
                }

                $order->update([
                    'paid_amount' => $applied,
                    'payment_status' => match (true) {
                        $applied <= 0 => PaymentStatus::Unpaid->value,
                        $applied >= $totalAmount => PaymentStatus::Paid->value,
                        default => PaymentStatus::Partial->value,
                    },
                ]);
            }

            return $order->fresh(['items.product', 'invoice', 'invoice.receipts']);
        });
    }

    /**
     * Settle a payment (full or partial) for an outstanding order at checkout.
     */
    public function settlePayment(Order $order, PaymentMethod $method, ?float $amount = null, ?int $paymentAccountId = null): SalesReceipt
    {
        if ($order->payment_status === PaymentStatus::Paid->value) {
            throw new \DomainException('Order ini sudah lunas.');
        }

        if (! $order->invoice) {
            throw new \DomainException('Faktur belum tersedia.');
        }

        return DB::transaction(function () use ($order, $method, $amount, $paymentAccountId) {
            $invoice = $order->invoice;
            $total = (float) $invoice->total_amount;
            $received = round((float) $invoice->receipts()->sum('gross_amount'), 2);
            $remaining = round($total - $received, 2);

            if ($remaining <= 0) {
                throw new \DomainException('Faktur ini sudah lunas.');
            }

            $payAmount = $amount === null
                ? $remaining
                : round(min(max((float) $amount, 0), $remaining), 2);

            if ($payAmount <= 0) {
                throw new \DomainException('Nominal pembayaran tidak valid.');
            }

            $account = $this->resolvePaymentAccount($method, $paymentAccountId);
            $receipt = $this->processPayment($invoice, $method, $payAmount, $account);

            $newReceived = round($received + $payAmount, 2);
            $order->update([
                'paid_amount' => $newReceived,
                'payment_status' => $newReceived >= $total
                    ? PaymentStatus::Paid->value
                    : PaymentStatus::Partial->value,
            ]);

            return $receipt;
        });
    }

    /**
     * Complete the order when food has been served.
     */
    public function completeOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::Completed->value]);

            return $order->fresh('items.product');
        });
    }

    /**
     * Issue a sales invoice for the order and post the accounting journal.
     */
    private function issueInvoice(Order $order, float $cogs): SalesInvoice
    {
        $invoice = SalesInvoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.$order->id,
            'total_amount' => $order->total_amount,
            'status' => InvoiceStatus::Issued->value,
            'issued_at' => now(),
        ]);

        $this->accountingService->postSalesInvoice($invoice, $cogs);

        return $invoice;
    }

    /**
     * Process a payment, recording the receipt and posting the journal.
     */
    private function processPayment(SalesInvoice $invoice, PaymentMethod $method, float $grossAmount, ?CashBankAccount $account = null): SalesReceipt
    {
        $mdrFee = round($grossAmount * $method->mdr_rate, 2);
        $netAmount = round($grossAmount - $mdrFee, 2);

        $receipt = SalesReceipt::create([
            'invoice_id' => $invoice->id,
            'payment_method' => $method->code,
            'payment_account_id' => $account?->id,
            'gross_amount' => $grossAmount,
            'mdr_fee' => $mdrFee,
            'net_amount' => $netAmount,
            'payment_date' => now(),
        ]);

        $received = round((float) $invoice->receipts()->sum('gross_amount'), 2);

        $invoice->update([
            'status' => $received >= (float) $invoice->total_amount
                ? InvoiceStatus::Paid->value
                : InvoiceStatus::Issued->value,
        ]);

        $this->accountingService->postSalesReceipt($receipt);

        return $receipt;
    }

    /**
     * Resolve the Cash & Bank account a payment should be credited to.
     *
     * An explicit account must belong to the chosen payment method; otherwise,
     * the method's default account is used, falling back to its first active
     * account. Cash silently routes to the cash method's account (Kas Utama).
     */
    private function resolvePaymentAccount(PaymentMethod $method, ?int $paymentAccountId = null): ?CashBankAccount
    {
        if ($paymentAccountId) {
            $account = CashBankAccount::query()->where('id', $paymentAccountId)->where('is_active', true)->first();

            if ($account && $account->payment_method_id === $method->id) {
                return $account;
            }
        }

        return CashBankAccount::query()
            ->where('payment_method_id', $method->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * Whether the cashier is allowed to accept partial payments up front.
     */
    private function prepayEnabled(): bool
    {
        $value = Setting::get('pos.cashier_enable_prepay', false);

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Decrease product stock within the wrapping transaction.
     */
    private function decrementStock(Product $product, int $qty): void
    {
        $product->decrement('stock', $qty);

        if ($product->fresh()->stock < 0) {
            throw new \DomainException("Stok {$product->name} tidak mencukupi.");
        }
    }

    /**
     * Generate the next order number for today.
     */
    private function nextOrderNumber(): string
    {
        $today = now()->format('Ymd');
        $last = Order::whereDate('created_at', now()->toDateString())
            ->where('order_number', 'like', "ORD-{$today}-%")
            ->orderByDesc('id')
            ->first();

        $next = $last ? ((int) Str::afterLast($last->order_number, '-')) + 1 : 1;

        return "ORD-{$today}-".str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
