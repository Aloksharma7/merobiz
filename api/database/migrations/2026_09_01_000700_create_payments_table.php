<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('payment_number', 50);
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('method')->default('cash');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'payment_number']);
            $table->index(['business_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
