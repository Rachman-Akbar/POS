<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('primary_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('sku', 100)->nullable()->unique();
            $table->enum('type', ['product', 'service'])->default('product')->index();
            $table->longText('description')->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('thumbnail')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->boolean('track_stock')->default(true)->index();
            $table->unsignedInteger('stock')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('published')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['primary_category_id', 'status', 'is_active'], 'products_catalog_idx');
            $table->index(['price', 'is_active'], 'products_price_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
