<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_item_writer_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('writer_id')->constrained()->restrictOnDelete();
            $table->date('assigned_from');
            $table->date('assigned_to')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['invoice_item_id', 'assigned_to'], 'item_writer_assignments_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_item_writer_assignments');
    }
};
