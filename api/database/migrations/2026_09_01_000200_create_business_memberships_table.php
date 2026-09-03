<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('salesperson');
            $table->string('title')->nullable();
            $table->decimal('commission_rate', 7, 4)->default(0);
            $table->boolean('active')->default(true);
            $table->date('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
            $table->index(['business_id', 'role', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_memberships');
    }
};
