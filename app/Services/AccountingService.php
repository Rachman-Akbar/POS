<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalDetail;
use App\Models\JournalEntry;
use App\Models\PaymentMethod;
use App\Models\SalesInvoice;
use App\Models\SalesReceipt;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingService
{
    public const ACCOUNT_RECEIVABLE = '1100';

    public const ACCOUNT_INVENTORY = '1200';

    public const ACCOUNT_CASH = '1300';

    public const ACCOUNT_BANK = '1310';

    public const ACCOUNT_QRIS = '1320';

    public const ACCOUNT_EWALLET = '1330';

    public const ACCOUNT_PPN_OUT = '2500';

    public const ACCOUNT_REVENUE = '4000';

    public const ACCOUNT_HPP = '5100';

    public const ACCOUNT_MDR = '5200';

    /**
     * Post the journal entry when a sales invoice is issued.
     *
     * Debit  Piutang Usaha (receivable)          total_amount
     * Debit  HPP                                  cogs
     * Credit Pendapatan Penjualan (revenue)       subtotal - discount
     * Credit Persediaan Bahan Baku (inventory)    cogs
     * Credit PPN Keluaran                         tax_amount
     */
    public function postSalesInvoice(SalesInvoice $invoice, float $cogs): JournalEntry
    {
        return DB::transaction(function () use ($invoice, $cogs) {
            $order = $invoice->order;
            $revenue = (float) $order->subtotal - (float) $order->discount;
            $tax = (float) $order->tax_amount;

            $entry = JournalEntry::create([
                'number' => $this->nextNumber('INV'),
                'type' => 'sales_invoice',
                'date' => now(),
                'description' => "Faktur {$invoice->invoice_number}",
                'reference_type' => SalesInvoice::class,
                'reference_id' => $invoice->id,
            ]);

            $this->postLine($entry, self::ACCOUNT_RECEIVABLE, $invoice->total_amount, 0.0);
            $this->postLine($entry, self::ACCOUNT_HPP, $cogs, 0.0);
            $this->postLine($entry, self::ACCOUNT_REVENUE, 0.0, $revenue);
            $this->postLine($entry, self::ACCOUNT_INVENTORY, 0.0, $cogs);
            if ($tax > 0) {
                $this->postLine($entry, self::ACCOUNT_PPN_OUT, 0.0, $tax);
            }

            return $entry->load('details');
        });
    }

    /**
     * Post the journal entry when a sales receipt (payment) is received.
     *
     * Debit  Kas / Bank / QRIS / Dompet Digital      net_amount
     * Debit  Biaya MDR / Admin                     mdr_fee
     * Credit Piutang Usaha                                         gross_amount
     */
    public function postSalesReceipt(SalesReceipt $receipt): JournalEntry
    {
        return DB::transaction(function () use ($receipt) {
            $entry = JournalEntry::create([
                'number' => $this->nextNumber('RCT'),
                'type' => 'sales_receipt',
                'date' => now(),
                'description' => "Penerimaan #{$receipt->id}",
                'reference_type' => SalesReceipt::class,
                'reference_id' => $receipt->id,
            ]);

            $this->postLine($entry, $this->settlementAccountFor($receipt), $receipt->net_amount, 0.0);
            if ($receipt->mdr_fee > 0) {
                $this->postLine($entry, self::ACCOUNT_MDR, $receipt->mdr_fee, 0.0);
            }
            $this->postLine($entry, self::ACCOUNT_RECEIVABLE, 0.0, $receipt->gross_amount);

            return $entry->load('details');
        });
    }

    /**
     * Post the journal entry that returns money to the customer.
     *
     * Jurnal pembalik, bukan hapus baris lama: pembukuan tetap bisa dibaca dari
     * awal sampai akhir, dan selisihnya selalu terlihat di akun yang sama.
     *
     * Retur berbasis nilai, bukan berdasarkan daftar barang, jadi hanya sisi
     * pendapatan dan kas yang dibalik. Piutang sengaja tidak disentuh:
     * pembayarannya sudah masuk kas saat penerimaan, dan mendebet piutang di
     * sini akan membuat saldo piutang negatif.
     *
     * Debit  Pendapatan Penjualan                          amount
     * Credit Kas / Bank / QRIS / Dompet Digital           net_amount
     * Credit Biaya MDR / Admin                            mdr_fee
     */
    public function postSalesRefund(SalesReceipt $receipt, float $amount, ?string $reason = null): JournalEntry
    {
        if ($amount <= 0) {
            throw new \DomainException('Nominal retur harus lebih dari nol.');
        }

        return DB::transaction(function () use ($receipt, $amount, $reason) {
            $mdr = $this->mdrShareOf($receipt, $amount);
            $netAmount = round($amount - $mdr, 2);

            $entry = JournalEntry::create([
                'number' => $this->nextNumber('REF'),
                'type' => 'sales_refund',
                'date' => now(),
                'description' => trim("Retur #{$receipt->id}".($reason ? " — {$reason}" : '')),
                'reference_type' => SalesReceipt::class,
                'reference_id' => $receipt->id,
            ]);

            $this->postLine($entry, self::ACCOUNT_REVENUE, $amount, 0.0);
            $this->postLine($entry, $this->settlementAccountFor($receipt), 0.0, $netAmount);

            if ($mdr > 0) {
                $this->postLine($entry, self::ACCOUNT_MDR, 0.0, $mdr);
            }

            return $entry->load('details');
        });
    }

    /**
     * Post the single journal entry that reverses a voided sales invoice.
     *
     * Pembatalan dibukukan sebagai satu entri, bukan "balik invoice" ditambah
     * "balik penerimaan" terpisah. Kalau dua entri dipakai, sisi piutang akan
     * dibalik dua kali: `-total_amount` dari reversal invoice dan `+total_amount`
     * dari reversal penerimaan, membuat piutang turun although uangnya sudah
     * masuk dan tidak pernah menjadi piutang lagi.
     *
     * Sisi kredit untuk uang yang sudah masuk diarahkan ke akun kas/bank/e-wallet
     * sesuai metode pembayaran, jadi hanya bagian yang benar-benar belum dibayar
     * yang tetap dihitung sebagai piutang.
     *
     * Debit  Pendapatan Penjualan               subtotal - discount
     * Debit  Persediaan Bahan Baku                    cogs
     * Debit  PPN Keluaran                        tax_amount
     * Credit Kas / Bank / QRIS / Dompet Digital   net_amount diterima
     * Credit Biaya MDR / Admin                   mdr_fee diterima
     * Credit Piutang Usaha              total - total yang sudah diterima
     * Credit HPP                                            cogs
     */
    public function postSalesInvoiceReversal(
        SalesInvoice $invoice,
        EloquentCollection $receipts,
        float $cogs,
        ?string $reason = null
    ): JournalEntry {
        return DB::transaction(function () use ($invoice, $receipts, $cogs, $reason) {
            $order = $invoice->order;
            $revenue = (float) $order->subtotal - (float) $order->discount;
            $tax = (float) $order->tax_amount;

            $settled = [];
            $mdrTotal = 0.0;
            $received = 0.0;

            foreach ($receipts as $receipt) {
                $received += (float) $receipt->gross_amount;

                $code = $this->settlementAccountFor($receipt);
                $settled[$code] = round(($settled[$code] ?? 0.0) + (float) $receipt->net_amount, 2);
                $mdrTotal += (float) $receipt->mdr_fee;
            }

            $mdrTotal = round($mdrTotal, 2);
            $unsettled = round((float) $invoice->total_amount - $received, 2);

            $entry = JournalEntry::create([
                'number' => $this->nextNumber('REV'),
                'type' => 'sales_invoice_reversal',
                'date' => now(),
                'description' => trim("Pembatalan {$invoice->invoice_number}".($reason ? " — {$reason}" : '')),
                'reference_type' => SalesInvoice::class,
                'reference_id' => $invoice->id,
            ]);

            $this->postLine($entry, self::ACCOUNT_REVENUE, $revenue, 0.0);
            $this->postLine($entry, self::ACCOUNT_INVENTORY, $cogs, 0.0);

            if ($tax > 0) {
                $this->postLine($entry, self::ACCOUNT_PPN_OUT, $tax, 0.0);
            }

            foreach ($settled as $code => $net) {
                if ($net > 0) {
                    $this->postLine($entry, $code, 0.0, $net);
                }
            }

            if ($mdrTotal > 0) {
                $this->postLine($entry, self::ACCOUNT_MDR, 0.0, $mdrTotal);
            }

            // Piutang hanya tersisa untuk bagian faktur yang belum dibayar.
            if ($unsettled > 0) {
                $this->postLine($entry, self::ACCOUNT_RECEIVABLE, 0.0, $unsettled);
            }

            $this->postLine($entry, self::ACCOUNT_HPP, 0.0, $cogs);

            return $entry->load('details');
        });
    }

    /**
     * Which balance sheet account a payment method settles into.
     *
     * Routing follows the method's `type` so every channel (bca, gopay, dana,
     * ...) lands on the right account instead of defaulting to cash. An unknown
     * method is treated as cash, which is the safe default for a till.
     */
    private function settlementAccountFor(SalesReceipt $receipt): string
    {
        $type = PaymentMethod::query()
            ->where('code', $receipt->payment_method)
            ->value('type');

        return match ($type) {
            'bank' => self::ACCOUNT_BANK,
            'qris' => self::ACCOUNT_QRIS,
            'ewallet' => self::ACCOUNT_EWALLET,
            default => self::ACCOUNT_CASH,
        };
    }

    /**
     * Create a validated journal entry line.
     */
    private function postLine(JournalEntry $entry, string $accountCode, float $debit, float $credit): void
    {
        $account = ChartOfAccount::where('code', $accountCode)->firstOrFail();

        JournalDetail::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => $debit,
            'credit' => $credit,
        ]);
    }

    /**
     * Generate the next sequential journal number in a given prefix group.
     *
     * Nomor dihitung dari baris paling besar, bukan dari jumlah baris, supaya
     * penghapusan jurnal lama tidak menyebabkan nomor dipakai ulang.
     */
    private function nextNumber(string $prefix): string
    {
        $type = match ($prefix) {
            'INV' => 'sales_invoice',
            'REF' => 'sales_refund',
            'REV' => 'sales_invoice_reversal',
            default => 'sales_receipt',
        };

        $last = JournalEntry::where('type', $type)->max('id');

        return Str::upper($prefix).'-'.str_pad((string) (($last ?? 0) + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Porsi MDR dari sebagian refund, proporsional terhadap nilai penuh
     * penerimaan. Biaya MDR dibalik ikut nama, jadi kas yang benar-benar
     * kembali ke pelanggan tetap sama dengan nominal yang dihitung kasir.
     */
    private function mdrShareOf(SalesReceipt $receipt, float $amount): float
    {
        $gross = (float) $receipt->gross_amount;

        if ($gross <= 0) {
            return 0.0;
        }

        return round((float) $receipt->mdr_fee * ($amount / $gross), 2);
    }
}
