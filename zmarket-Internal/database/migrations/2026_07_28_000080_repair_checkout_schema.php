<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'primary_category_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->unsignedBigInteger('primary_category_id')->nullable()->after('id');
            });

            if (Schema::hasColumn('products', 'category_id')) {
                DB::table('products')
                    ->whereNull('primary_category_id')
                    ->update(['primary_category_id' => DB::raw('category_id')]);
            }
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('orders', 'guest_name')) {
                    $table->string('guest_name', 120)->default('');
                }
                if (! Schema::hasColumn('orders', 'guest_phone')) {
                    $table->string('guest_phone', 30)->default('');
                }
                if (! Schema::hasColumn('orders', 'guest_address')) {
                    $table->text('guest_address')->nullable();
                }
                if (! Schema::hasColumn('orders', 'guest_notes')) {
                    $table->text('guest_notes')->nullable();
                }
                if (! Schema::hasColumn('orders', 'subtotal')) {
                    $table->decimal('subtotal', 15, 2)->default(0);
                }
                if (! Schema::hasColumn('orders', 'total_amount')) {
                    $table->decimal('total_amount', 15, 2)->default(0);
                }
                if (! Schema::hasColumn('orders', 'payment_method')) {
                    $table->string('payment_method', 30)->default('internal_billing');
                }
                if (! Schema::hasColumn('orders', 'payment_status')) {
                    $table->string('payment_status', 30)->default('unpaid');
                }
                if (! Schema::hasColumn('orders', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable();
                }
                if (! Schema::hasColumn('orders', 'cancel_reason')) {
                    $table->text('cancel_reason')->nullable();
                }
                if (! Schema::hasColumn('orders', 'admin_notes')) {
                    $table->longText('admin_notes')->nullable();
                }
                if (! Schema::hasColumn('orders', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('order_items', 'product_sku')) {
                    $table->string('product_sku', 100)->nullable();
                }
                if (! Schema::hasColumn('order_items', 'product_type')) {
                    $table->string('product_type', 30)->default('product');
                }
            });
        }

        if (! Schema::hasTable('order_status_histories')) {
            Schema::create('order_status_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void {}
};
