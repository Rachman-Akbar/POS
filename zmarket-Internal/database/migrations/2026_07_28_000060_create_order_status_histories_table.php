<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('from_status', ['pending', 'confirmed', 'processing', 'completed', 'cancelled'])->nullable();
            $table->enum('to_status', ['pending', 'confirmed', 'processing', 'completed', 'cancelled']);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at'], 'order_history_order_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
