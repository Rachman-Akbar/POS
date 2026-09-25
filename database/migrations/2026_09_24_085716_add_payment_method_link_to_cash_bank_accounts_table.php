<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cash_bank_accounts', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('type')->constrained('payment_methods')->nullOnDelete();
            $table->boolean('is_default')->default(false)->after('payment_method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_bank_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn('is_default');
        });
    }
};
