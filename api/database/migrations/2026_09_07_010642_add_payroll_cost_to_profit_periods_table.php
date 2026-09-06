<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_periods', function (Blueprint $table): void {
            // What was actually paid out through payroll (any pay type, plus written
            // off loans) during this period — the real deduction behind net_profit.
            // `commissions` stays as the informational accrued-commission snapshot.
            $table->decimal('payroll_cost', 18, 2)->default(0)->after('commissions');
        });
    }

    public function down(): void
    {
        Schema::table('profit_periods', function (Blueprint $table): void {
            $table->dropColumn('payroll_cost');
        });
    }
};
