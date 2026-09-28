<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                if (! Schema::hasColumn('products', 'sku')) {
                    $table->string('sku', 100)->nullable()->after('slug');
                }
                if (! Schema::hasColumn('products', 'price')) {
                    $table->decimal('price', 15, 2)->default(0)->after('thumbnail');
                }
                if (! Schema::hasColumn('products', 'track_stock')) {
                    $table->boolean('track_stock')->default(true)->after('price');
                }
                if (! Schema::hasColumn('products', 'stock')) {
                    $table->unsignedInteger('stock')->nullable()->after('track_stock');
                }
            });

            if (Schema::hasTable('product_variants')) {
                $hasDefault = Schema::hasColumn('product_variants', 'is_default');
                $hasActive = Schema::hasColumn('product_variants', 'is_active');
                $hasTrackStock = Schema::hasColumn('product_variants', 'track_stock');

                DB::table('products')
                    ->orderBy('id')
                    ->get()
                    ->each(function (object $product) use ($hasDefault, $hasActive, $hasTrackStock): void {
                        $query = DB::table('product_variants')
                            ->where('product_id', $product->id);

                        if ($hasDefault) {
                            $query->orderByDesc('is_default');
                        }

                        if ($hasActive) {
                            $query->orderByDesc('is_active');
                        }

                        $variant = $query->orderBy('id')->first();

                        if (! $variant) {
                            return;
                        }

                        DB::table('products')->where('id', $product->id)->update([
                            'sku' => $variant->sku ?? null,
                            'price' => $variant->price ?? 0,
                            'track_stock' => $hasTrackStock ? (bool) $variant->track_stock : true,
                            'stock' => isset($variant->stock) ? max(0, (int) $variant->stock) : 0,
                        ]);
                    });
            }

            $this->ensureUniqueIndex('products', 'products_sku_unique', ['sku']);
        }

        if (Schema::hasTable('order_items')) {
            $this->dropForeignForColumn('order_items', 'product_variant_id');

            Schema::table('order_items', function (Blueprint $table): void {
                foreach (['product_variant_id', 'variant_name', 'variant_attributes'] as $column) {
                    if (Schema::hasColumn('order_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('product_variant_values');
        Schema::dropIfExists('product_variants');

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                foreach (['rating', 'review_count'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    public function down(): void {}

    private function dropForeignForColumn(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        $database = DB::getDatabaseName();
        $constraint = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($constraint) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                str_replace('`', '``', $table),
                str_replace('`', '``', $constraint)
            ));
        }
    }

    private function ensureUniqueIndex(string $table, string $name, array $columns): void
    {
        $indexes = Schema::getIndexes($table);
        $exists = collect($indexes)->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);

        if (! $exists) {
            Schema::table($table, function (Blueprint $blueprint) use ($name, $columns): void {
                $blueprint->unique($columns, $name);
            });
        }
    }
};
