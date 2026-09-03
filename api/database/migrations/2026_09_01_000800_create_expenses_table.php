<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category');
            $table->string('vendor')->nullable();
            $table->date('expense_date');
            $table->decimal('amount', 18, 2);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->string('payment_method')->default('cash');
            $table->string('status')->default('pending');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'expense_date', 'status']);
            $table->index(['business_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
