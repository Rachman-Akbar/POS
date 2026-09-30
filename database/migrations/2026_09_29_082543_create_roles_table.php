<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kelompok hak akses. Backward-compatible dengan kolom `users.role` lama:
     * kolom tersebut tetap dipakai, sementara tabel ini menjadi sumber kebenaran
     * permission. `is_system` menandai role bawaan yang tidak boleh dihapus.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('slug', 80)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
