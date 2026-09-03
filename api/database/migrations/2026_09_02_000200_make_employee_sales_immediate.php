<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MeroBiz no longer uses an admin approval step for sales. Existing pending
        // entries are accepted immediately so current databases upgrade cleanly.
        DB::table('invoices')
            ->where('verification_status', 'pending')
            ->update([
                'verification_status' => 'verified',
                'verified_by' => DB::raw('created_by'),
                'verified_at' => DB::raw('COALESCE(finalized_at, created_at)'),
                'verification_note' => null,
            ]);
    }

    public function down(): void
    {
        // Intentionally irreversible: there is no reliable way to know which legacy
        // sales were pending before this migration accepted them.
    }
};
