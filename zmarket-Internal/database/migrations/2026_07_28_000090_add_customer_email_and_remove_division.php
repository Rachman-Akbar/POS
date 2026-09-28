<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->id();
                $table->string('email', 190)->unique();
                $table->string('name', 120);
                $table->string('phone', 30)->nullable();
                $table->text('address')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('orders', 'customer_id')) {
                    $table->unsignedBigInteger('customer_id')->nullable()->after('order_number')->index();
                }

                if (! Schema::hasColumn('orders', 'guest_email')) {
                    $table->string('guest_email', 190)->nullable()->after('customer_id')->index();
                }
            });

            DB::table('orders')
                ->orderBy('id')
                ->get()
                ->each(function (object $order): void {
                    $email = filled($order->guest_email ?? null)
                        ? mb_strtolower(trim((string) $order->guest_email))
                        : 'legacy-order-'.$order->id.'@marketplace.local';

                    $customerId = DB::table('customers')->where('email', $email)->value('id');

                    if (! $customerId) {
                        $customerId = DB::table('customers')->insertGetId([
                            'email' => $email,
                            'name' => (string) ($order->guest_name ?? 'Legacy Buyer'),
                            'phone' => $order->guest_phone ?? null,
                            'address' => $order->guest_address ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('orders')->where('id', $order->id)->update([
                        'customer_id' => $customerId,
                        'guest_email' => $email,
                    ]);
                });

            if (Schema::hasColumn('orders', 'guest_division')) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->dropColumn('guest_division');
                });
            }
        }
    }

    public function down(): void {}
};
