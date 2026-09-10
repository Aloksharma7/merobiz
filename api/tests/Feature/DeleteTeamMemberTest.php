<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteTeamMemberTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Delete Member Co', 'slug' => 'delete-member-co-'.uniqid(), 'code' => 'DM'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'DM', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_an_admin_can_remove_an_employee_from_the_team(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dtm1@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-dtm1@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $membership = $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true]);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/businesses/{$business->id}/team/{$membership->id}")->assertOk();

        $this->assertSoftDeleted('business_memberships', ['id' => $membership->id]);
        $this->getJson("/api/businesses/{$business->id}/team")->assertOk()->assertJsonMissing(['id' => $membership->id]);
    }

    public function test_an_employee_cannot_remove_a_team_member(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dtm2@example.test', 'password' => 'password']);
        $employeeOne = User::query()->create(['name' => 'Employee One', 'email' => 'employee-dtm2a@example.test', 'password' => 'password']);
        $employeeTwo = User::query()->create(['name' => 'Employee Two', 'email' => 'employee-dtm2b@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $business->memberships()->create(['user_id' => $employeeOne->id, 'role' => BusinessRole::Employee, 'active' => true]);
        $membershipTwo = $business->memberships()->create(['user_id' => $employeeTwo->id, 'role' => BusinessRole::Employee, 'active' => true]);

        Sanctum::actingAs($employeeOne);
        $this->deleteJson("/api/businesses/{$business->id}/team/{$membershipTwo->id}")->assertForbidden();
        $this->assertDatabaseHas('business_memberships', ['id' => $membershipTwo->id, 'deleted_at' => null]);
    }

    public function test_removing_a_member_preserves_their_payroll_history(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dtm3@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-dtm3@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $membership = $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'fixed_salary', 'salary_amount' => 20000, 'joined_at' => today()->toDateString(),
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 10000, 'method' => 'bank_transfer',
        ])->assertCreated();
        $paymentId = $membership->salaryPayments()->latest('id')->first()->id;

        $this->deleteJson("/api/businesses/{$business->id}/team/{$membership->id}")->assertOk();

        // Soft-deleting the membership must not cascade-delete money that was
        // actually paid out — that history has to survive on the books.
        $this->assertDatabaseHas('salary_payments', ['id' => $paymentId, 'amount' => 10000]);
    }

    public function test_the_founder_cannot_be_removed_by_someone_else(): void
    {
        $founder = User::query()->create(['name' => 'Founder', 'email' => 'founder-dtm4@example.test', 'password' => 'password']);
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin-dtm4@example.test', 'password' => 'password']);
        $business = $this->business($founder);
        $founderMembership = $business->memberships()->where('user_id', $founder->id)->first();
        $business->memberships()->create(['user_id' => $admin->id, 'role' => BusinessRole::Admin, 'active' => true]);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/businesses/{$business->id}/team/{$founderMembership->id}")->assertForbidden();
        $this->assertDatabaseHas('business_memberships', ['id' => $founderMembership->id, 'deleted_at' => null]);
    }

    public function test_a_business_cannot_be_left_without_any_active_owner(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dtm5@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $ownerMembership = $business->memberships()->where('user_id', $owner->id)->first();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/businesses/{$business->id}/team/{$ownerMembership->id}")
            ->assertStatus(422);
        $this->assertDatabaseHas('business_memberships', ['id' => $ownerMembership->id, 'deleted_at' => null]);
    }

    public function test_only_a_full_control_owner_can_remove_another_owner(): void
    {
        $founder = User::query()->create(['name' => 'Founder', 'email' => 'founder-dtm6@example.test', 'password' => 'password']);
        $coOwnerB = User::query()->create(['name' => 'Co Owner B', 'email' => 'coownerb-dtm6@example.test', 'password' => 'password']);
        $coOwnerC = User::query()->create(['name' => 'Co Owner C', 'email' => 'coownerc-dtm6@example.test', 'password' => 'password']);
        $business = $this->business($founder);
        $business->memberships()->create(['user_id' => $coOwnerB->id, 'role' => BusinessRole::Owner, 'full_control' => false, 'active' => true]);
        $membershipC = $business->memberships()->create(['user_id' => $coOwnerC->id, 'role' => BusinessRole::Owner, 'full_control' => false, 'active' => true]);

        // Neither is the founder or the target, so this isolates the
        // full-control-required-to-remove-an-owner check from the separate
        // founder-protection check exercised above.
        Sanctum::actingAs($coOwnerB);
        $this->deleteJson("/api/businesses/{$business->id}/team/{$membershipC->id}")->assertForbidden();
        $this->assertDatabaseHas('business_memberships', ['id' => $membershipC->id, 'deleted_at' => null]);
    }
}
