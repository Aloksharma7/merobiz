<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfitShareViaTeamFormTest extends TestCase
{
    use RefreshDatabase;

    private function makeBusiness(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Profit Share Co', 'slug' => 'profit-share-co-'.uniqid(), 'code' => 'PS'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'PS', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true, 'commission_rate' => 0]);

        return $business;
    }

    public function test_a_full_control_owner_can_set_a_profit_share_while_adding_a_member(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-psf@example.test', 'password' => 'password']);
        $business = $this->makeBusiness($owner);

        Sanctum::actingAs($owner);
        $response = $this->postJson("/api/businesses/{$business->id}/team", [
            'name' => 'Staffer', 'email' => 'staffer-psf@example.test', 'password' => 'password123',
            'role' => 'employee', 'commission_rate' => 20,
            'ownership_percent' => 0, 'profit_share_percent' => 20,
        ])->assertCreated();

        $rows = $this->getJson("/api/businesses/{$business->id}/team")->assertOk()->json('data');
        $staffer = collect($rows)->firstWhere('email', 'staffer-psf@example.test');
        $this->assertEquals(20.0, $staffer['profit_share_percent']);
        $this->assertEquals(20.0, $staffer['commission_rate']);
    }

    public function test_a_non_full_control_admin_cannot_set_a_profit_share(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-psf2@example.test', 'password' => 'password']);
        $business = $this->makeBusiness($owner);
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin-psf2@example.test', 'password' => 'password']);
        $business->memberships()->create(['user_id' => $admin->id, 'role' => BusinessRole::Admin, 'active' => true]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/businesses/{$business->id}/team", [
            'name' => 'Staffer', 'email' => 'staffer-psf2@example.test', 'password' => 'password123',
            'role' => 'employee', 'profit_share_percent' => 20,
        ])->assertForbidden();
    }

    public function test_a_full_control_owner_can_update_an_existing_members_profit_share(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-psf3@example.test', 'password' => 'password']);
        $business = $this->makeBusiness($owner);
        $staffer = User::query()->create(['name' => 'Staffer', 'email' => 'staffer-psf3@example.test', 'password' => 'password']);
        $membership = $business->memberships()->create(['user_id' => $staffer->id, 'role' => BusinessRole::Employee, 'active' => true, 'commission_rate' => 20]);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/businesses/{$business->id}/team/{$membership->id}", [
            'ownership_percent' => 0, 'profit_share_percent' => 20,
        ])->assertOk();

        $ownerships = $this->getJson("/api/businesses/{$business->id}/ownerships")->assertOk()->json('data');
        $stafferPeriod = collect($ownerships)->firstWhere('user_id', $staffer->id);
        $this->assertNotNull($stafferPeriod);
        $this->assertEquals(20.0, $stafferPeriod['profit_share_percent']);
    }

    public function test_combined_profit_share_still_cannot_exceed_100_percent(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-psf4@example.test', 'password' => 'password']);
        $business = $this->makeBusiness($owner);
        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/ownerships", [
            'user_id' => $owner->id, 'ownership_percent' => 80, 'profit_share_percent' => 80, 'effective_from' => today()->toDateString(),
        ])->assertCreated();

        $this->postJson("/api/businesses/{$business->id}/team", [
            'name' => 'Staffer', 'email' => 'staffer-psf4@example.test', 'password' => 'password123',
            'role' => 'employee', 'ownership_percent' => 0, 'profit_share_percent' => 30,
        ])->assertUnprocessable();
    }
}
