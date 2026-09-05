<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_writer_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('writer_id')->constrained()->restrictOnDelete();
            $table->date('assigned_from');
            $table->date('assigned_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_writer_assignments');
    }
};
