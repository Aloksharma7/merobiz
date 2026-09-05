<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_income_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('other');
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'active']);
        });

        Schema::create('personal_income_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('personal_income_sources')->restrictOnDelete();
            $table->date('entry_date');
            $table->decimal('amount', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'entry_date']);
        });

        Schema::create('personal_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('vendor')->nullable();
            $table->date('expense_date');
            $table->decimal('amount', 18, 2);
            $table->string('payment_method')->default('cash');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'expense_date']);
            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_expenses');
        Schema::dropIfExists('personal_income_entries');
        Schema::dropIfExists('personal_income_sources');
    }
};
