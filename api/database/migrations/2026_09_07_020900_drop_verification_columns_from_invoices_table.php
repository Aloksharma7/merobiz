<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            // Leftover from a sale-approval workflow removed earlier — every invoice
            // was stamped "verified" the instant it was created and nothing ever read
            // these columns back (not a query, not the API response, nothing).
            $table->dropColumn(['verification_status', 'verified_by', 'verified_at', 'verification_note']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('verification_status', 20)->default('verified')->after('status');
            $table->unsignedBigInteger('verified_by')->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('verification_note')->nullable()->after('verified_at');
        });
    }
};
