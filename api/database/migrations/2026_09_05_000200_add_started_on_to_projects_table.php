<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->date('started_on')->nullable()->after('client_email');
        });

        // Backfill existing projects with their creation date so older rows still sort/filter sensibly.
        DB::table('projects')->whereNull('started_on')->update(['started_on' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('started_on');
        });
    }
};
