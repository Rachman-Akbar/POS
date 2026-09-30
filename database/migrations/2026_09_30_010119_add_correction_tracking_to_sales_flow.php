<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak koreksi transaksi: pembatalan (void) dan retur pembayaran.
     *
     * Semua kolom nullable dan tidak mengubah data yang sudah ada. Tujuannya
     * satu: setiap uang yang keluar dari kas karena kesalahan kasir harus bisa
     * ditelusuri kembali ke siapa, kapan, dan alasan apa.
     */
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            // HPP disimpan saat faktur terbit, bukan dihitung ulang saat void.
            // Kalau harga pokok produk berubah setelah penjualan, reversal jurnal
            // tetap memakai angka yang sama seperti saat penjualan, sehingga
            // pembatalan benar-benar meniadakan jurnal aslinya.
            $table->decimal('cogs_total', 12, 2)->default(0)->after('total_amount');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('voided_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->text('void_reason')->nullable()->after('voided_at');
        });

        Schema::table('sales_receipts', function (Blueprint $table): void {
            $table->decimal('refund_amount', 12, 2)->default(0)->after('net_amount');
            $table->foreignId('refunded_by')->nullable()->after('refund_amount')->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable()->after('refunded_by');
            $table->text('refund_reason')->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales_receipts', function (Blueprint $table): void {
            $table->dropColumn(['refund_amount', 'refunded_by', 'refunded_at', 'refund_reason']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['voided_by', 'voided_at', 'void_reason']);
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropColumn('cogs_total');
        });
    }
};
