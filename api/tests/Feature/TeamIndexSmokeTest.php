<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamIndexSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_index_does_not_error_on_a_vanilla_business_with_no_ownership_records(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-tis@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-tis@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Vanilla Co', 'slug' => 'vanilla-co-'.uniqid(), 'code' => 'VC'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'VC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        // No 'pay_type', 'commission_rate' etc. passed at all — exactly what old
        // production rows look like, relying purely on column defaults.
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true]);

        Sanctum::actingAs($owner);
        $this->getJson("/api/businesses/{$business->id}/team?start=2026-09-01&end=2026-09-07")->assertOk();
    }
}
