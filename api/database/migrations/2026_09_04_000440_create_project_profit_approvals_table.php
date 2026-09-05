<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_profit_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->date('approved_on');
            $table->decimal('amount', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'approved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_profit_approvals');
    }
};
