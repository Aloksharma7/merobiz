<?php

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\BusinessMembership;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_memberships', function (Blueprint $table): void {
            $table->boolean('full_control')->default(false)->after('role');
        });

        // Whoever actually founded a business always keeps full control of it.
        Business::query()->each(function (Business $business): void {
            BusinessMembership::query()
                ->where('business_id', $business->id)
                ->where('user_id', $business->owner_id)
                ->where('role', BusinessRole::Owner->value)
                ->update(['full_control' => true]);
        });
    }

    public function down(): void
    {
        Schema::table('business_memberships', function (Blueprint $table): void {
            $table->dropColumn('full_control');
        });
    }
};
