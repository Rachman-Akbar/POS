<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalDetail;
use App\Models\JournalEntry;
use App\Models\SalesInvoice;
use App\Models\SalesReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingService
{
    public const ACCOUNT_RECEIVABLE = '1100';

    public const ACCOUNT_INVENTORY = '1200';

    public const ACCOUNT_CASH = '1300';

    public const ACCOUNT_BANK = '1310';

    public const ACCOUNT_QRIS = '1320';

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
     * Debit  Kas / Bank / QRIS                     net_amount
     * Debit  Biaya MDR / Admin                     mdr_fee
     * Credit Piutang Usaha                                         gross_amount
     */
    public function postSalesReceipt(SalesReceipt $receipt): JournalEntry
    {
        return DB::transaction(function () use ($receipt) {
            $account = match ($receipt->payment_method) {
                'bank' => self::ACCOUNT_BANK,
                'qris' => self::ACCOUNT_QRIS,
                default => self::ACCOUNT_CASH,
            };

            $entry = JournalEntry::create([
                'number' => $this->nextNumber('RCT'),
                'type' => 'sales_receipt',
                'date' => now(),
                'description' => "Penerimaan #{$receipt->id}",
                'reference_type' => SalesReceipt::class,
                'reference_id' => $receipt->id,
            ]);

            $this->postLine($entry, $account, $receipt->net_amount, 0.0);
            if ($receipt->mdr_fee > 0) {
                $this->postLine($entry, self::ACCOUNT_MDR, $receipt->mdr_fee, 0.0);
            }
            $this->postLine($entry, self::ACCOUNT_RECEIVABLE, 0.0, $receipt->gross_amount);

            return $entry->load('details');
        });
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
     */
    private function nextNumber(string $prefix): string
    {
        $count = JournalEntry::where('type', '=', match ($prefix) {
            'INV' => 'sales_invoice',
            default => 'sales_receipt',
        })->count();

        return Str::upper($prefix).'-'.str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}
