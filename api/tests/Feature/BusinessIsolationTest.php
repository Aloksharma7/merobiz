<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_cannot_open_an_unassigned_business(): void
    {
        $member = User::query()->create(['name' => 'Member', 'email' => 'member@example.test', 'password' => 'password']);
        $otherOwner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);

        $assigned = $this->business($member, 'Assigned Company', 'ASSIGNED');
        $unassigned = $this->business($otherOwner, 'Private Company', 'PRIVATE');
        $assigned->memberships()->create(['user_id' => $member->id, 'role' => BusinessRole::Salesperson, 'active' => true]);

        Sanctum::actingAs($member);

        $this->getJson("/api/businesses/{$unassigned->id}")->assertForbidden();
    }

    public function test_salesperson_cannot_read_business_profit_report(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2@example.test', 'password' => 'password']);
        $salesperson = User::query()->create(['name' => 'Sales', 'email' => 'sales@example.test', 'password' => 'password']);
        $business = $this->business($owner, 'Sales Company', 'SALES');
        $business->memberships()->create(['user_id' => $salesperson->id, 'role' => BusinessRole::Salesperson, 'active' => true]);

        Sanctum::actingAs($salesperson);

        $this->getJson("/api/businesses/{$business->id}/reports/profit-loss")->assertForbidden();
    }

    private function business(User $owner, string $name, string $code): Business
    {
        return Business::query()->create([
            'owner_id' => $owner->id,
            'name' => $name,
            'slug' => strtolower($code),
            'code' => $code,
            'business_type' => 'service',
            'currency' => 'NPR',
            'invoice_prefix' => $code,
            'default_tax_rate' => 0,
            'status' => 'active',
        ]);
    }
}
