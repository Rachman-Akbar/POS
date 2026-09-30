<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Email pelanggan jadi boleh kosong.
     *
     * Di layar kasir, mendaftarkan pelanggan cukup nama; email dan telepon
     * bisa dilengkapi belakangan dari menu admin. Kolomnya sebelumnya NOT NULL
     * karena form lama mewajibkan email, jadi hanya nullability yang dilepas —
     * tidak ada data yang dihapus atau diubah.
     *
     * Unique index tetap aman: MySQL dan SQLite mengizinkan banyak NULL pada
     * kolom unique, jadi pelanggan tanpa email tidak saling bentrok.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Hanya nullability yang berubah. Index unique tidak ikut ditulis
            // ulang di sini karena sudah ada dan akan bentrok kalau dideklarasi
            // dua kali.
            $table->string('email', 190)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email', 190)->nullable(false)->change();
        });
    }
};
