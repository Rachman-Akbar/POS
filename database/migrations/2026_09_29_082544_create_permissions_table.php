<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permission granular per modul/action. `name` disimpan sebagai slug
     * (mis. `product.create`), `module` untuk pengelompokan di UI.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('module', 60)->index();
            $table->string('label', 120);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
