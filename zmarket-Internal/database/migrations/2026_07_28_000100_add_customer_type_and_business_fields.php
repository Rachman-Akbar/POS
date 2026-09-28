<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table): void {
                if (! Schema::hasColumn('customers', 'customer_type')) {
                    $table->string('customer_type', 20)->default('individual')->after('email')->index();
                }
                if (! Schema::hasColumn('customers', 'nik')) {
                    $table->string('nik', 32)->nullable()->after('address');
                }
                if (! Schema::hasColumn('customers', 'npwp')) {
                    $table->string('npwp', 32)->nullable()->after('nik');
                }
                if (! Schema::hasColumn('customers', 'province')) {
                    $table->string('province', 120)->nullable()->after('npwp');
                }
                if (! Schema::hasColumn('customers', 'city')) {
                    $table->string('city', 120)->nullable()->after('province');
                }
                if (! Schema::hasColumn('customers', 'company_name')) {
                    $table->string('company_name', 180)->nullable()->after('city');
                }
                if (! Schema::hasColumn('customers', 'postal_code')) {
                    $table->string('postal_code', 16)->nullable()->after('company_name');
                }
                if (! Schema::hasColumn('customers', 'country')) {
                    $table->string('country', 100)->nullable()->after('postal_code');
                }
            });

            $this->normalizeCustomers();
            $this->ensureCustomerEmailUnique();
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('orders', 'customer_type')) {
                    $table->string('customer_type', 20)->default('individual')->after('customer_id')->index();
                }
                if (! Schema::hasColumn('orders', 'guest_nik')) {
                    $table->string('guest_nik', 32)->nullable()->after('guest_address');
                }
                if (! Schema::hasColumn('orders', 'guest_npwp')) {
                    $table->string('guest_npwp', 32)->nullable()->after('guest_nik');
                }
                if (! Schema::hasColumn('orders', 'guest_province')) {
                    $table->string('guest_province', 120)->nullable()->after('guest_npwp');
                }
                if (! Schema::hasColumn('orders', 'guest_city')) {
                    $table->string('guest_city', 120)->nullable()->after('guest_province');
                }
                if (! Schema::hasColumn('orders', 'guest_company_name')) {
                    $table->string('guest_company_name', 180)->nullable()->after('guest_city');
                }
                if (! Schema::hasColumn('orders', 'guest_postal_code')) {
                    $table->string('guest_postal_code', 16)->nullable()->after('guest_company_name');
                }
                if (! Schema::hasColumn('orders', 'guest_country')) {
                    $table->string('guest_country', 100)->nullable()->after('guest_postal_code');
                }
            });

            DB::table('orders')->whereNull('customer_type')->orWhere('customer_type', '')->update([
                'customer_type' => 'individual',
            ]);

            DB::table('orders')
                ->whereNotNull('customer_id')
                ->orderBy('id')
                ->get()
                ->each(function (object $order): void {
                    $customer = DB::table('customers')->where('id', $order->customer_id)->first();

                    if (! $customer) {
                        return;
                    }

                    DB::table('orders')->where('id', $order->id)->update([
                        'customer_type' => $customer->customer_type ?: 'individual',
                        'guest_nik' => $customer->nik,
                        'guest_npwp' => $customer->npwp,
                        'guest_province' => $customer->province,
                        'guest_city' => $customer->city,
                        'guest_company_name' => $customer->company_name,
                        'guest_postal_code' => $customer->postal_code,
                        'guest_country' => $customer->country,
                    ]);
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                foreach ([
                    'customer_type',
                    'guest_nik',
                    'guest_npwp',
                    'guest_province',
                    'guest_city',
                    'guest_company_name',
                    'guest_postal_code',
                    'guest_country',
                ] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table): void {
                foreach ([
                    'customer_type',
                    'nik',
                    'npwp',
                    'province',
                    'city',
                    'company_name',
                    'postal_code',
                    'country',
                ] as $column) {
                    if (Schema::hasColumn('customers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function normalizeCustomers(): void
    {
        DB::table('customers')
            ->orderBy('id')
            ->get()
            ->groupBy(function (object $customer): string {
                $email = mb_strtolower(trim((string) $customer->email));

                return $email !== '' ? $email : 'customer-'.$customer->id.'@marketplace.local';
            })
            ->each(function ($group, string $email): void {
                $keep = $group->first();
                $duplicateIds = $group->skip(1)->pluck('id');

                if ($duplicateIds->isNotEmpty()) {
                    if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'customer_id')) {
                        DB::table('orders')->whereIn('customer_id', $duplicateIds)->update([
                            'customer_id' => $keep->id,
                            'guest_email' => $email,
                        ]);
                    }

                    DB::table('customers')->whereIn('id', $duplicateIds)->delete();
                }

                DB::table('customers')->where('id', $keep->id)->update([
                    'email' => $email,
                    'customer_type' => in_array($keep->customer_type ?? null, ['individual', 'business'], true)
                        ? $keep->customer_type
                        : 'individual',
                ]);
            });
    }

    private function ensureCustomerEmailUnique(): void
    {
        $indexes = Schema::getIndexes('customers');

        $hasUniqueEmail = collect($indexes)->contains(function (array $index): bool {
            return (bool) ($index['unique'] ?? false)
                && array_map('strtolower', $index['columns'] ?? []) === ['email'];
        });

        if (! $hasUniqueEmail) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->unique('email', 'customers_email_unique');
            });
        }
    }
};
