<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profit_allocation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->date('distribution_date');
            $table->decimal('amount', 18, 2);
            $table->string('method')->default('bank_transfer');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'distribution_date']);
            $table->index(['user_id', 'distribution_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_distributions');
    }
};
