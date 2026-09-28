<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_type', 20)->default('individual')->index();
            $table->string('guest_email', 190)->index();
            $table->string('guest_name', 120);
            $table->string('guest_phone', 30)->index();
            $table->text('guest_address');
            $table->string('guest_nik', 32)->nullable();
            $table->string('guest_npwp', 32)->nullable();
            $table->string('guest_province', 120)->nullable();
            $table->string('guest_city', 120)->nullable();
            $table->string('guest_company_name', 180)->nullable();
            $table->string('guest_postal_code', 16)->nullable();
            $table->string('guest_country', 100)->nullable();
            $table->text('guest_notes')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->enum('status', ['pending', 'confirmed', 'processing', 'completed', 'cancelled'])
                ->default('pending')
                ->index();
            $table->enum('payment_method', ['cod', 'bank_transfer', 'internal_billing'])
                ->default('internal_billing');
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid')->index();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->text('cancel_reason')->nullable();
            $table->longText('admin_notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['status', 'created_at'], 'orders_status_created_idx');
            $table->index(['customer_id', 'created_at'], 'orders_customer_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
