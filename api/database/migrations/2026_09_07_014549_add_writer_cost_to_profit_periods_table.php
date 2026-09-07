<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_periods', function (Blueprint $table): void {
            $table->decimal('writer_cost', 18, 2)->default(0)->after('payroll_cost');
        });
    }

    public function down(): void
    {
        Schema::table('profit_periods', function (Blueprint $table): void {
            $table->dropColumn('writer_cost');
        });
    }
};
