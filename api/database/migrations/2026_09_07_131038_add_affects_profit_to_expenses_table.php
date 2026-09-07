<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            // Default true keeps every existing expense behaving exactly as before —
            // it still reduces net profit. Set to false when the expense is just cash
            // catching up to a cost already recognized as COGS on an invoice item, so
            // it still reduces available balance without deducting from profit twice.
            $table->boolean('affects_profit')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn('affects_profit');
        });
    }
};
