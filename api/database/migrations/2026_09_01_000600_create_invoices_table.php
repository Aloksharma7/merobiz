<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('invoice_number', 50);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('cost_amount', 18, 2)->default(0);
            $table->decimal('commission_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('balance_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'invoice_number']);
            $table->index(['business_id', 'invoice_date', 'status']);
            $table->index(['business_id', 'created_by', 'invoice_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('cost_price', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_rate', 7, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
