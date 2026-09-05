<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('withdrawn_on');
            $table->decimal('amount', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'withdrawn_on']);
            $table->index(['business_id', 'user_id', 'withdrawn_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_withdrawals');
    }
};
