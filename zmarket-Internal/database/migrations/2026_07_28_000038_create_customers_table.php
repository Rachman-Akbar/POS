<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('customer_type', 20)->default('individual')->index();
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('nik', 32)->nullable();
            $table->string('npwp', 32)->nullable();
            $table->string('province', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('company_name', 180)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('country', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
