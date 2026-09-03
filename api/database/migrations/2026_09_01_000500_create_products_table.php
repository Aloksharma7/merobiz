<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->string('type')->default('service');
            $table->string('unit', 30)->default('unit');
            $table->decimal('sale_price', 18, 2)->default(0);
            $table->decimal('cost_price', 18, 2)->default(0);
            $table->decimal('tax_rate', 7, 2)->default(0);
            $table->boolean('track_inventory')->default(false);
            $table->decimal('stock_quantity', 18, 3)->default(0);
            $table->decimal('reorder_level', 18, 3)->default(0);
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'sku']);
            $table->index(['business_id', 'type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
