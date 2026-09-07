<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every business here now runs purely on profit share, not sales commission.
     * This moves existing data to match: no membership keeps a commission pay
     * type or a leftover commission rate, so no invoice created after this can
     * still generate a commission amount. Reversible by hand later if commission
     * is ever needed again — this only touches data, not the schema.
     */
    public function up(): void
    {
        DB::table('business_memberships')->update([
            'pay_type' => 'fixed_salary',
            'commission_rate' => 0,
        ]);
    }

    public function down(): void
    {
        // Data-only change; the original per-member commission rates and pay
        // types aren't recoverable once overwritten.
    }
};
