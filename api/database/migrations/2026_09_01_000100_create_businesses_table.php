<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code', 20)->unique();
            $table->string('business_type')->default('service');
            $table->string('currency', 3)->default('NPR');
            $table->string('pan_number', 30)->nullable();
            $table->string('vat_number', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('invoice_prefix', 20)->default('INV');
            $table->unsignedBigInteger('invoice_next_number')->default(1);
            $table->unsignedBigInteger('payment_next_number')->default(1);
            $table->decimal('default_tax_rate', 7, 2)->default(0);
            $table->string('status')->default('active');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
