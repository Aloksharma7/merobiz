<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('client_name');
            $table->string('client_phone')->nullable();
            $table->string('client_email')->nullable();
            $table->text('topic');
            $table->string('course');
            $table->string('work');
            $table->string('work_status')->default('started');
            $table->decimal('deal_amount', 18, 2);
            $table->decimal('writer_payment_amount', 18, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'work_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
