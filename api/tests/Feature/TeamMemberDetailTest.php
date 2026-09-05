<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamMemberDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_view_a_single_members_detail_but_an_employee_cannot(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-detail@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-detail@example.test', 'password' => 'password']);
        $other = User::query()->create(['name' => 'Other', 'email' => 'other-detail@example.test', 'password' => 'password']);

        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Detail Co', 'slug' => 'detail-co-'.uniqid(), 'code' => 'DC'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'DC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $membership = $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true, 'title' => 'Sales Rep',
            'pay_type' => 'fixed_salary', 'salary_amount' => 15000, 'joined_at' => today()->toDateString(),
        ]);

        $otherBusiness = Business::query()->create([
            'owner_id' => $other->id, 'name' => 'Other Co', 'slug' => 'other-co-'.uniqid(), 'code' => 'OC'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'OC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $foreignMembership = $otherBusiness->memberships()->create(['user_id' => $other->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/businesses/{$business->id}/team/{$membership->id}")->assertOk();
        $response->assertJsonPath('data.name', 'Employee');
        $response->assertJsonPath('data.title', 'Sales Rep');
        $response->assertJsonPath('data.pay_type', 'fixed_salary');
        $response->assertJsonPath('data.salary_amount', 15000);

        // A membership belonging to a different business is not reachable.
        $this->getJson("/api/businesses/{$business->id}/team/{$foreignMembership->id}")->assertNotFound();

        // An employee (without team.manage) cannot view anyone's detail, including their own.
        Sanctum::actingAs($employee);
        $this->getJson("/api/businesses/{$business->id}/team/{$membership->id}")->assertForbidden();
    }
}
