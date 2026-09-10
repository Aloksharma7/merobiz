<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResetMemberPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Reset Pw Co', 'slug' => 'reset-pw-co-'.uniqid(), 'code' => 'RP'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'RP', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_an_admin_can_reset_an_employees_password(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-rmp1@example.test', 'password' => 'password']);
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin-rmp1@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-rmp1@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $business->memberships()->create(['user_id' => $admin->id, 'role' => BusinessRole::Admin, 'active' => true]);
        $membership = $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/reset-password", [
            'password' => 'NewPassw0rd', 'password_confirmation' => 'NewPassw0rd',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassw0rd', $employee->fresh()->password));
    }

    public function test_an_admin_can_reset_the_owners_password(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-rmp2@example.test', 'password' => 'password']);
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin-rmp2@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $ownerMembership = $business->memberships()->where('user_id', $owner->id)->first();
        $business->memberships()->create(['user_id' => $admin->id, 'role' => BusinessRole::Admin, 'active' => true]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/businesses/{$business->id}/team/{$ownerMembership->id}/reset-password", [
            'password' => 'OwnerNewPass1', 'password_confirmation' => 'OwnerNewPass1',
        ])->assertOk();

        $this->assertTrue(Hash::check('OwnerNewPass1', $owner->fresh()->password));
    }

    public function test_an_employee_cannot_reset_anyones_password(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-rmp3@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-rmp3@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $membership = $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true]);

        Sanctum::actingAs($employee);
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/reset-password", [
            'password' => 'ShouldNotWork1', 'password_confirmation' => 'ShouldNotWork1',
        ])->assertForbidden();
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-rmp4@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-rmp4@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $membership = $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/reset-password", [
            'password' => 'Mismatch1Pass', 'password_confirmation' => 'Different1Pass',
        ])->assertUnprocessable();
    }
}
