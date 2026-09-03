<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cumulative upgrade safety: some installations applied the immediate-sales
        // patch without first applying the role/sales schema migration.
        if (Schema::hasTable('business_memberships')) {
            DB::table('business_memberships')
                ->whereIn('role', ['manager', 'accountant', 'salesperson', 'cashier', 'viewer'])
                ->update(['role' => 'employee']);
        }

        if (! Schema::hasTable('invoices')) {
            return;
        }

        $needsStatus = ! Schema::hasColumn('invoices', 'verification_status');
        $needsVerifiedBy = ! Schema::hasColumn('invoices', 'verified_by');
        $needsVerifiedAt = ! Schema::hasColumn('invoices', 'verified_at');
        $needsNote = ! Schema::hasColumn('invoices', 'verification_note');

        if ($needsStatus || $needsVerifiedBy || $needsVerifiedAt || $needsNote) {
            Schema::table('invoices', function (Blueprint $table) use ($needsStatus, $needsVerifiedBy, $needsVerifiedAt, $needsNote): void {
                if ($needsStatus) {
                    $table->string('verification_status', 20)->default('verified')->after('status');
                }
                if ($needsVerifiedBy) {
                    // Keep this nullable and unconstrained in the repair migration.
                    // The application only uses it as an audit reference; this avoids
                    // failing upgrades on installations with unusual FK state.
                    $table->unsignedBigInteger('verified_by')->nullable()->after('verification_status');
                }
                if ($needsVerifiedAt) {
                    $table->timestamp('verified_at')->nullable()->after('verified_by');
                }
                if ($needsNote) {
                    $table->text('verification_note')->nullable()->after('verified_at');
                }
            });
        }

        // There is no approval workflow. These values merely keep older v2.1 code
        // compatible while every sale is treated as accepted immediately.
        DB::table('invoices')->update([
            'verification_status' => 'verified',
            'verified_at' => DB::raw('COALESCE(verified_at, finalized_at, created_at)'),
        ]);
    }

    public function down(): void
    {
        // Intentionally no-op: this is a compatibility/repair migration.
    }
};
