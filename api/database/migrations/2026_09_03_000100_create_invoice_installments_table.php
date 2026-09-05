<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->date('due_date');
            $table->decimal('amount', 18, 2);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['invoice_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_installments');
    }
};
