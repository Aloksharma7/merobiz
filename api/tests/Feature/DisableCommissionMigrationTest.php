<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisableCommissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_converts_existing_commission_memberships_to_fixed_salary(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dcm@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Disable Commission Co', 'slug' => 'disable-commission-co-'.uniqid(), 'code' => 'DC'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'DC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $membership = $business->memberships()->create([
            'user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true,
            'pay_type' => 'commission', 'commission_rate' => 15,
        ]);

        (require database_path('migrations/2026_09_07_135404_disable_commission_on_all_memberships.php'))->up();

        $membership->refresh();
        $this->assertSame('fixed_salary', $membership->pay_type->value);
        $this->assertEquals(0.0, (float) $membership->commission_rate);
    }
}
