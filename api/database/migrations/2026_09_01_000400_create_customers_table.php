<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('pan_number', 30)->nullable();
            $table->text('address')->nullable();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
