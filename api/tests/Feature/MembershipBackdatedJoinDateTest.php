<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipBackdatedJoinDateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function business(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Payroll Co', 'slug' => 'payroll-co-'.uniqid(), 'code' => 'PC'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'PC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_backdating_join_date_when_registering_lets_a_backdated_salary_payment_not_read_as_overpaid(): void
    {
        Carbon::setTestNow('2025-06-15');

        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-bjd1@example.test', 'password' => 'password']);
        $business = $this->business($owner);

        Sanctum::actingAs($owner);
        // Registered today, but they actually started back in April — three months
        // (Apr, May, Jun) should already be accrued, not just the current month.
        $member = $this->postJson("/api/businesses/{$business->id}/team", [
            'name' => 'Late Registered Employee', 'email' => 'employee-bjd1@example.test', 'password' => 'password12',
            'role' => 'employee', 'joined_at' => '2025-04-01', 'pay_type' => 'fixed_salary', 'salary_amount' => 2000,
        ])->assertCreated()->json('member');

        $this->assertSame('2025-04-01', $member['joined_at']);

        $summary = $this->getJson("/api/businesses/{$business->id}/team/{$member['id']}/salary")->assertOk()->json('summary');
        $this->assertSame(3, $summary['months_elapsed']);
        $this->assertEquals(6000.0, $summary['accrued']);

        // Paying out all three months' back pay at once must not look like an
        // overpayment now that the join date reflects when they actually started.
        $this->postJson("/api/businesses/{$business->id}/team/{$member['id']}/salary/payments", [
            'payment_date' => '2025-06-10', 'amount' => 6000, 'method' => 'bank_transfer',
        ])->assertCreated()->assertJsonPath('summary.pending', 0);
    }

    public function test_correcting_join_date_after_the_fact_fixes_an_already_registered_members_accrual(): void
    {
        Carbon::setTestNow('2025-06-15');

        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-bjd2@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-bjd2@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $membership = $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'fixed_salary', 'salary_amount' => 2000, 'joined_at' => '2025-06-15',
        ]);

        Sanctum::actingAs($owner);
        // Only registered/joined today by the system's record — one month accrued.
        $before = $this->getJson("/api/businesses/{$business->id}/team/{$membership->id}/salary")->assertOk()->json('summary');
        $this->assertSame(1, $before['months_elapsed']);
        $this->assertEquals(2000.0, $before['accrued']);

        // Correcting the join date to when they actually started fixes the accrual.
        $this->patchJson("/api/businesses/{$business->id}/team/{$membership->id}", [
            'joined_at' => '2025-04-01',
        ])->assertOk()->assertJsonPath('member.joined_at', '2025-04-01');

        $after = $this->getJson("/api/businesses/{$business->id}/team/{$membership->id}/salary")->assertOk()->json('summary');
        $this->assertSame(3, $after['months_elapsed']);
        $this->assertEquals(6000.0, $after['accrued']);
    }

    public function test_registering_without_a_join_date_still_defaults_to_today(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-bjd3@example.test', 'password' => 'password']);
        $business = $this->business($owner);

        Sanctum::actingAs($owner);
        $member = $this->postJson("/api/businesses/{$business->id}/team", [
            'name' => 'Fresh Hire', 'email' => 'employee-bjd3@example.test', 'password' => 'password12', 'role' => 'employee',
        ])->assertCreated()->json('member');

        $this->assertSame(today()->toDateString(), $member['joined_at']);
    }
}
