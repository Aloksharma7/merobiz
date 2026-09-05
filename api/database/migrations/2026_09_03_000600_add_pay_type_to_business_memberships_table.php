<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_memberships', function (Blueprint $table): void {
            $table->string('pay_type')->default('commission')->after('commission_rate');
            $table->decimal('salary_amount', 18, 2)->default(0)->after('pay_type');
            $table->boolean('salary_visible_to_staff')->default(false)->after('salary_amount');
        });
    }

    public function down(): void
    {
        Schema::table('business_memberships', function (Blueprint $table): void {
            $table->dropColumn(['pay_type', 'salary_amount', 'salary_visible_to_staff']);
        });
    }
};
