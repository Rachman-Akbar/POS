<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master data kategori. `products.category` tetap menjadi penanda produk,
     * nilainya disalin dari nama kategori di tabel ini.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        if (! Schema::hasTable('products')) {
            return;
        }

        $names = DB::table('products')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        $now = now();

        foreach ($names as $position => $name) {
            DB::table('categories')->updateOrInsert(
                ['name' => $name],
                ['sort_order' => $position, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
