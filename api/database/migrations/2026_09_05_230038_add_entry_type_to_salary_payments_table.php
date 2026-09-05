<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_payments', function (Blueprint $table): void {
            // payment: a normal payroll payout. advance: an early payout that still
            // counts against what's owed (can push it negative). loan: money given
            // that does NOT count against payroll — tracked as a separate balance
            // until settled. write_off: an adjustment that reduces an outstanding
            // loan balance without any new money changing hands.
            $table->string('entry_type')->default('payment')->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('salary_payments', function (Blueprint $table): void {
            $table->dropColumn('entry_type');
        });
    }
};
