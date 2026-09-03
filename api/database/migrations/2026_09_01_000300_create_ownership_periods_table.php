<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ownership_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('ownership_percent', 7, 4);
            $table->decimal('profit_share_percent', 7, 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'user_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ownership_periods');
    }
};
