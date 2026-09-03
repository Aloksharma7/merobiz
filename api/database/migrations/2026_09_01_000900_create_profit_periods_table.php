<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('closed');
            $table->decimal('net_sales', 18, 2)->default(0);
            $table->decimal('tax_collected', 18, 2)->default(0);
            $table->decimal('cost_of_sales', 18, 2)->default(0);
            $table->decimal('gross_profit', 18, 2)->default(0);
            $table->decimal('expenses', 18, 2)->default(0);
            $table->decimal('commissions', 18, 2)->default(0);
            $table->decimal('net_profit', 18, 2)->default(0);
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'start_date', 'end_date']);
        });

        Schema::create('profit_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profit_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('effective_profit_share_percent', 7, 4)->default(0);
            $table->decimal('allocated_amount', 18, 2)->default(0);
            $table->decimal('distributed_amount', 18, 2)->default(0);
            $table->timestamps();
            $table->unique(['profit_period_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_allocations');
        Schema::dropIfExists('profit_periods');
    }
};
