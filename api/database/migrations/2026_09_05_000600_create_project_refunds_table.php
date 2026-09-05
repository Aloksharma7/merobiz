<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('refunded_by')->constrained('users')->restrictOnDelete();
            $table->date('refunded_on');
            $table->decimal('amount', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'refunded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_refunds');
    }
};
