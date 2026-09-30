<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\ItemStatus;
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
     * @param  array{discount?: float, tax_rate?: float|null, payment_method?: string|null, payment_account_id?: int|null, paid_amount?: float|null, notes?: string|null, customer_id?: int|null}  $options
     */
    public function createOrder(?User $user, string $tableNumber, PaymentType $paymentType, array $items, array $options = []): Order
    {
        return DB::transaction(function () use ($user, $tableNumber, $paymentType, $items, $options) {
            $order = Order::create([
                'order_number' => $this->nextOrderNumber(),
                'table_number' => $tableNumber ?: null,
                'customer_id' => $options['customer_id'] ?? null,
                'user_id' => $user?->id,
                'payment_type' => $paymentType->value,
                'status' => OrderStatus::Pending->value,
                'payment_status' => PaymentStatus::Unpaid->value,
                'paid_amount' => 0,
                'notes' => $options['notes'] ?? null,
            ]);

            [$subtotal, $cogs] = $this->attachItems($order, $items, reserveStock: true);
            $this->applyTotals($order, $subtotal, $options);

            $totalAmount = (float) $order->total_amount;
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
     * Save an order as a draft: a temporary, unprocessed record. It is persisted
     * so the cashier can find it again from the Pesanan list, but it creates no
     * invoice, reserves no stock, posts no journal and never reaches the
     * kitchen. A draft only becomes a real order once it is finalized.
     *
     * @param  array<int, array{qty: int, product_id: int, notes?: string|null}>  $items
     * @param  array{discount?: float, tax_rate?: float|null, notes?: string|null, customer_id?: int|null}  $options
     */
    public function saveDraft(?User $user, string $tableNumber, array $items, array $options = []): Order
    {
        return DB::transaction(function () use ($user, $tableNumber, $items, $options) {
            $order = Order::create([
                'order_number' => $this->nextOrderNumber(),
                'table_number' => $tableNumber ?: null,
                'customer_id' => $options['customer_id'] ?? null,
                'user_id' => $user?->id,
                'payment_type' => PaymentType::PayLater->value,
                'status' => OrderStatus::Draft->value,
                'payment_status' => PaymentStatus::Unpaid->value,
                'paid_amount' => 0,
                'notes' => $options['notes'] ?? null,
            ]);

            [$subtotal] = $this->attachItems($order, $items, reserveStock: false);
            $this->applyTotals($order, $subtotal, $options);

            return $order->fresh(['items.product', 'invoice', 'customer']);
        });
    }

    /**
     * Continue a draft: reserve its stock, raise the sales invoice, post the
     * journal and optionally record the payment, then move the order into the
     * normal (pending) flow so it reaches the kitchen.
     *
     * @param  array{payment_method?: string|null, payment_account_id?: int|null, paid_amount?: float|null}  $options
     */
    public function finalizeDraft(Order $order, array $options = []): Order
    {
        return DB::transaction(function () use ($order, $options) {
            // Lock baris order supaya dua permintaan lanjutkan bersamaan tidak
            // bisa sama-sama menerbitkan invoice dan menarik stok dua kali.
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Draft->value) {
                throw new \DomainException('Pesanan ini bukan draft.');
            }

            $lines = $locked->items()->get();

            if ($lines->isEmpty()) {
                throw new \DomainException('Draft tidak memiliki item.');
            }

            $cogs = 0.0;

            foreach ($lines as $item) {
                $product = Product::where('is_active', true)->findOrFail($item->product_id);
                $this->decrementStock($product, $item->qty);
                $cogs += $item->qty * $product->cost_price;
            }

            $invoice = $this->issueInvoice($locked, $cogs);
            $totalAmount = (float) $locked->total_amount;

            $tendered = isset($options['paid_amount']) && $options['paid_amount'] !== null
                ? (float) $options['paid_amount']
                : 0.0;

            $applied = round(min(max($tendered, 0), $totalAmount), 2);

            if ($applied > 0) {
                $method = PaymentMethod::where('code', $options['payment_method'] ?? 'cash')->where('is_active', true)->first()
                    ?? PaymentMethod::where('code', 'cash')->firstOrFail();
                $account = $this->resolvePaymentAccount($method, $options['payment_account_id'] ?? null);
                $this->processPayment($invoice, $method, $applied, $account);
            }

            $locked->update([
                'status' => OrderStatus::Pending->value,
                'payment_type' => $applied >= $totalAmount && $totalAmount > 0
                    ? PaymentType::PayNow->value
                    : PaymentType::PayLater->value,
                'paid_amount' => $applied,
                'payment_status' => match (true) {
                    $applied <= 0 => PaymentStatus::Unpaid->value,
                    $applied >= $totalAmount => PaymentStatus::Paid->value,
                    default => PaymentStatus::Partial->value,
                },
            ]);

            return $locked->fresh(['items.product', 'invoice', 'invoice.receipts', 'customer']);
        });
    }

    /**
     * Edit isi sebuah draft sebelum diproses.
     *
     * Kasir dibolehkan mengubah isi draft: menambah atau menghapus menu,
     * mengubah jumlah, mengganti meja, sampai menyesuaikan diskon dan PPN.
     * Karena draft belum menyentuh invoice, stok, dan jurnal, item lama cukup
     * dihapus lalu dibuat ulang sesuai isi terbaru tanpa efek berantai.
     *
     * Order yang sudah diproses ditolak di sini. Koreksi salah input pada
     * transaksi yang sudah jadi memakai `correctOrder()`, yang permission-nya
     * terpisah (`transaction.correct`) dan selalu tercatat di audit log.
     *
     * @param  array<int, array{qty: int, product_id: int, notes?: string|null}>  $items
     * @param  array{discount?: float, tax_rate?: float|null, notes?: string|null, customer_id?: int|null}  $options
     */
    public function updateDraft(Order $order, string $tableNumber, array $items, array $options = []): Order
    {
        return DB::transaction(function () use ($order, $tableNumber, $items, $options) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Draft->value) {
                throw new \DomainException('Pesanan ini bukan draft.');
            }

            if (empty($items)) {
                throw new \DomainException('Draft tidak memiliki item.');
            }

            $this->rewriteContents($locked, $tableNumber, $items, $options);

            return $locked->fresh(['items.product', 'invoice', 'customer']);
        });
    }

    /**
     * Koreksi isi transaksi yang sudah diproses.
     *
     * Salah input di kasir tidak selalu berarti transaksi harus dibatalkan:
     * menu ketukik dua kali, jumlah terbalik, atau meja salah hanya perlu isi
     * pesanan yang diperbaiki. Karena itu koreksi di sini mengubah menu,
     * jumlah, meja, diskon, dan PPN tanpa menyentuh apa pun yang sudah
     * tercatat: stok tidak ditarik ulang, jurnal tetap, dan pembayaran yang
     * sudah diterima tidak berubah.
     *
     * Catatan koreksi ditambahkan ke catatan transaksi, bukan menggantikannya,
     * supaya alasan dari setiap perbaikan tetap terbaca pada pesanannya.
     * Koreksi terhadap transaksi yang sudah dibatalkan ditolak: pembatalan
     * sudah membalik seluruhnya, jadi isinya tidak boleh diubah lagi.
     *
     * @param  array<int, array{qty: int, product_id: int, notes?: string|null}>  $items
     * @param  array{discount?: float, tax_rate?: float|null, notes?: string|null, customer_id?: int|null}  $options
     */
    public function correctOrder(Order $order, string $tableNumber, array $items, array $options = []): Order
    {
        return DB::transaction(function () use ($order, $tableNumber, $items, $options) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw new \DomainException('Transaksi ini sudah dibatalkan.');
            }

            if (empty($items)) {
                throw new \DomainException('Pesanan tidak memiliki item.');
            }

            // Catatan koreksi menyusul catatan yang sudah ada, bukan
            // menggantikannya, supaya alasan koreksi lama tidak hilang.
            if (isset($options['notes'])) {
                $options['notes'] = $this->appendNote($locked->notes, (string) $options['notes']);
            }

            $this->rewriteContents($locked, $tableNumber, $items, $options);

            return $locked->fresh(['items.product', 'invoice', 'invoice.receipts', 'customer']);
        });
    }

    /**
     * Tulis ulang isi order: item lama dihapus, lalu item baru dibuat dari
     * daftar terbaru dan subtotal, diskon, PPN, serta total dihitung ulang.
     *
     * Dipakai editor draft maupun koreksi admin. Keduanya sama-sama tidak
     * menyentuh stok: draft memang belum menarik stok, sedangkan koreksi
     * hanya memperbaiki isi tanpa memakai atau mengembalikan stok yang sudah
     * tercatat.
     *
     * @param  array<int, array{qty: int, product_id: int, notes?: string|null}>  $items
     * @param  array{discount?: float, tax_rate?: float|null, notes?: string|null, customer_id?: int|null}  $options
     */
    private function rewriteContents(Order $order, string $tableNumber, array $items, array $options): void
    {
        $order->items()->delete();

        [$subtotal] = $this->attachItems($order, $items, reserveStock: false);

        $order->update([
            'table_number' => $tableNumber ?: null,
            'customer_id' => $options['customer_id'] ?? $order->customer_id,
            'notes' => $options['notes'] ?? $order->notes,
        ]);

        $this->applyTotals($order, $subtotal, $options);

        // Total invoice ikut diselaraskan supaya struk tidak mencetak angka yang
        // berbeda dengan pesanan.
        if ($order->invoice instanceof SalesInvoice) {
            $order->invoice->update(['total_amount' => $order->total_amount]);
        }
    }

    /**
     * Gabungkan catatan baru ke catatan lama, dipotong ke panjang kolom.
     */
    private function appendNote(?string $existing, string $note): ?string
    {
        $note = trim($note);

        if ($note === '') {
            return $existing;
        }

        $combined = trim(($existing ?? '')."\n".$note);

        return substr($combined, 0, 1000);
    }

    /**
     * Hapus draft sepenuhnya.
     *
     * Draft tidak membentuk invoice, tidak mempunyai jurnal, dan tidak menarik
     * stok, jadi penghapusannya cukup menghapus item dan row order — tidak ada
     * efek berantai yang harus dibatalkan. Order yang bukan draft menolak
     * operasi ini supaya transaksi yang sudah diproses tidak bisa hilang.
     */
    public function deleteDraft(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Draft->value) {
                throw new \DomainException('Pesanan ini bukan draft.');
            }

            $locked->items()->delete();
            $locked->delete();
        });
    }

    /**
     * Create the order item rows and return [subtotal, cogs]. When
     * $reserveStock is false (drafts) stock is not decremented and a short
     * stock is tolerated until the draft is finalized.
     *
     * @param  array<int, array{qty: int, product_id: int, notes?: string|null}>  $items
     * @return array{0: float, 1: float}
     */
    private function attachItems(Order $order, array $items, bool $reserveStock): array
    {
        $subtotal = 0.0;
        $cogs = 0.0;

        foreach ($items as $line) {
            $product = Product::where('is_active', true)->findOrFail($line['product_id']);
            $qty = max(1, (int) $line['qty']);

            if ($reserveStock) {
                $this->decrementStock($product, $qty);
            }

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

        return [$subtotal, $cogs];
    }

    /**
     * Compute discount/tax/total and store them on the order.
     *
     * @param  array{discount?: float, tax_rate?: float|null}  $options
     */
    private function applyTotals(Order $order, float $subtotal, array $options): void
    {
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
    }

    /**
     * Settle a payment (full or partial) for an outstanding order at checkout.
     *
     * The cashier may tender more than the outstanding balance; only the
     * balance is ever recorded as a receipt and the surplus is handed back as
     * change, so overpayment can never inflate revenue or the journal.
     *
     * @return array{receipt: SalesReceipt, change: float, applied: float}
     */
    public function settlePayment(Order $order, PaymentMethod $method, ?float $amount = null, ?int $paymentAccountId = null): array
    {
        // Pembatalan menutup invoice untuk selamanya. Tanpa penjaga ini kasir
        // bisa menerima pembayaran untuk transaksi yang sudah dibatalkan admin,
        // dan uang itu hilang tanpa pernah masuk pembukuan.
        if ($order->isVoided() || $order->invoice?->isVoided()) {
            throw new \DomainException('Transaksi ini sudah dibatalkan.');
        }

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
            $tendered = $amount === null ? $remaining : round(max((float) $amount, 0), 2);
            $payAmount = round(min($tendered, $remaining), 2);

            if ($payAmount <= 0) {
                throw new \DomainException('Nominal pembayaran tidak valid.');
            }

            $change = round(max($tendered - $payAmount, 0), 2);

            $account = $this->resolvePaymentAccount($method, $paymentAccountId);
            $receipt = $this->processPayment($invoice, $method, $payAmount, $account);

            $newReceived = round($received + $payAmount, 2);
            $order->update([
                'paid_amount' => $newReceived,
                'payment_status' => $newReceived >= $total
                    ? PaymentStatus::Paid->value
                    : PaymentStatus::Partial->value,
            ]);

            return [
                'receipt' => $receipt,
                'applied' => $payAmount,
                'change' => $change,
            ];
        });
    }

    /**
     * Complete the order when food has been served.
     */
    public function completeOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            if ($order->isVoided()) {
                throw new \DomainException('Pesanan ini sudah dibatalkan.');
            }

            $order->update(['status' => OrderStatus::Completed->value]);

            return $order->fresh('items.product');
        });
    }

    /**
     * Batalkan satu transaksi: kembalikan stok, batalkan faktur, kembalikan
     * seluruh pembayaran yang sudah masuk, dan reversal jurnal pembukuan.
     *
     * Void dipakai untuk pembatalan total. Retur sebagian tetap lewat
     * {@see self::refundPayment()} supaya tidak ada dua cara untuk mengembalikan
     * uang dan penjualan yang dibatalkan tidak/dobel ter-refund.
     *
     * @return array{order: Order, refunded: float}
     */
    public function voidOrder(Order $order, ?User $actor, string $reason): array
    {
        return DB::transaction(function () use ($order, $actor, $reason) {
            // Baris pesanan dikunci supaya void dan pembayaran tidak bisa
            // berjalan bersamaan atas invoice yang sama.
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw new \DomainException('Pesanan ini sudah dibatalkan.');
            }

            $invoice = $locked->invoice()->first();
            $refunded = 0.0;

            // Uang yang sudah masuk wajib kembali, karena void berarti penjualan
            // ini tidak pernah terjadi. Faktur yang belum dibayar tidak perlu
            // disentuh: tidak ada uang yang perlu dikembalikan.
            if ($invoice) {
                $receipts = $invoice->receipts()->lockForUpdate()->get();

                foreach ($receipts as $receipt) {
                    $refunded += $receipt->refundableAmount();
                }

                // Satu entri pembatalan untuk seluruh efeknya: pendapatan, HPP,
                // persediaan, PPN, dan uang yang keluar. Kred piutang hanya untuk
                // bagian faktur yang belum dibayar, karena bagian yang sudah
                // masuk kas harus keluar lewat akun kas, bukan lewat piutang.
                $this->accountingService->postSalesInvoiceReversal(
                    $invoice,
                    $receipts,
                    (float) $invoice->cogs_total,
                    $reason
                );

                foreach ($receipts as $receipt) {
                    $refundable = $receipt->refundableAmount();

                    if ($refundable > 0) {
                        $this->markRefunded($receipt, $refundable, $actor, $reason);
                    }
                }

                $invoice->update(['status' => InvoiceStatus::Void->value]);
            }

            // Draft tidak pernah menahan stok, jadi hanya pesanan yang sudah
            // terbit faktur yang perlu mengembalikan stok.
            if ($invoice) {
                foreach ($locked->items as $item) {
                    Product::whereKey($item->product_id)->increment('stock', (int) $item->qty);
                }
            }

            $locked->update([
                'status' => OrderStatus::Void->value,
                'payment_status' => PaymentStatus::Refunded->value,
                'paid_amount' => 0,
                'voided_by' => $actor?->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            foreach ($locked->items as $item) {
                $item->update(['status' => ItemStatus::Cancelled->value]);
            }

            return [
                'order' => $locked->fresh(['items.product', 'invoice.receipts', 'customer']),
                'refunded' => round($refunded, 2),
            ];
        });
    }

    /**
     * Kembalikan sebagian atau seluruh pembayaran ke pelanggan.
     *
     * Nominal retur tidak boleh melebihi sisa pembayaran yang belum diretur,
     * jadi kas tidak mungkin keluar lebih besar dari yang pernah masuk.
     *
     * @return array{order: Order, receipt: SalesReceipt, amount: float, reason: string}
     */
    public function refundPayment(Order $order, SalesReceipt $receipt, ?float $amount, ?User $actor, string $reason): array
    {
        return DB::transaction(function () use ($order, $receipt, $amount, $actor, $reason) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw new \DomainException('Pesanan ini sudah dibatalkan, tidak bisa diretur.');
            }

            $invoice = $locked->invoice ?? throw new \DomainException('Faktur belum tersedia.');
            $lockedReceipt = $invoice->receipts()->lockForUpdate()->find($receipt->getKey())
                ?? throw new \DomainException('Penerimaan pembayaran tidak ditemukan pada pesanan ini.');

            $refundable = $lockedReceipt->refundableAmount();

            if ($refundable <= 0) {
                throw new \DomainException('Penerimaan ini sudah diretur seluruhnya.');
            }

            $value = round($amount === null ? $refundable : max((float) $amount, 0), 2);

            if ($value <= 0) {
                throw new \DomainException('Nominal retur harus lebih dari nol.');
            }

            if ($value > $refundable + 0.009) {
                throw new \DomainException('Nominal retur melebihi sisa pembayaran yang bisa diretur.');
            }

            $this->applyRefund($lockedReceipt, $value, $actor, $reason);

            // `gross` tidak pernah berubah karena retur; yang berkurang adalah
            // `refund_amount`. Kas yang benar-benar ditahan kasir =
            // gross - refund, sedangkan yang belum pernah dibayar =
            // total - gross. Status pembayaran diturunkan dari dua angka itu,
            // bukan dari `paid_amount` saja, supaya transaksi yang sudah
            // diretur penuh tidak salah tampil sebagai "Lunas".
            $received = round((float) $invoice->receipts()->sum('gross_amount'), 2);
            $refunded = round((float) $invoice->receipts()->sum('refund_amount'), 2);
            $retained = round($received - $refunded, 2);
            $total = (float) $invoice->total_amount;

            $locked->update([
                'paid_amount' => $retained,
                'payment_status' => match (true) {
                    $refunded > 0 && $retained <= 0 => PaymentStatus::Refunded->value,
                    $received + 0.009 >= $total && $retained + 0.009 >= $total => PaymentStatus::Paid->value,
                    $retained > 0 => PaymentStatus::Partial->value,
                    default => PaymentStatus::Unpaid->value,
                },
            ]);

            if ($received + 0.009 < $total) {
                $invoice->update(['status' => InvoiceStatus::Issued->value]);
            }

            return [
                'order' => $locked->fresh(['items.product', 'invoice.receipts', 'customer']),
                'receipt' => $lockedReceipt->fresh(),
                'amount' => $value,
                'reason' => $reason,
            ];
        });
    }

    /**
     * Catat retur pada satu penerimaan dan posting jurnal pembalik.
     *
     * Nilai retur Discharge dihitung dari jumlah yang sudah diretur sebelumnya,
     * jadi retur bertahap pada satu penerimaan tidak menimpa nilai sebelumnya.
     */
    private function applyRefund(SalesReceipt $receipt, float $amount, ?User $actor, string $reason): void
    {
        $this->accountingService->postSalesRefund($receipt, $amount, $reason);

        $this->markRefunded($receipt, $amount, $actor, $reason);
    }

    /**
     * Tandai uang pada satu penerimaan sebagai sudah kembali ke pelanggan.
     *
     * Dipisah dari posting jurnal karena pembatalan menandai seluruh penerimaan
     * sekaligus lewat satu entri pembatalan, bukan satu jurnal per penerimaan.
     */
    private function markRefunded(SalesReceipt $receipt, float $amount, ?User $actor, string $reason): void
    {
        $receipt->forceFill([
            'refund_amount' => round((float) $receipt->refund_amount + $amount, 2),
            'refunded_by' => $actor?->id,
            'refunded_at' => now(),
            'refund_reason' => $reason,
        ])->save();
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
            'cogs_total' => $cogs,
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
