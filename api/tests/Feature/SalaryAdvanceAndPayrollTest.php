<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalaryAdvanceAndPayrollTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Payroll Co', 'slug' => 'payroll-co-'.uniqid(), 'code' => 'PC'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'PC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_an_advance_that_counts_against_pay_can_push_pending_negative_and_carries_forward(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-adv@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-adv@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $membership = $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'fixed_salary', 'salary_amount' => 2000, 'joined_at' => today()->toDateString(),
        ]);

        Sanctum::actingAs($owner);
        // Owed 2000 this month (1 month elapsed), advance 5000 counting against pay
        // -> pending goes to -3000, exactly the example this feature was built from.
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 5000, 'method' => 'cash', 'entry_type' => 'advance',
        ])->assertCreated()->assertJsonPath('summary.pending', -3000);

        $summary = $this->getJson("/api/businesses/{$business->id}/team/{$membership->id}/salary")->assertOk()->json('summary');
        $this->assertEquals(-3000.0, $summary['pending']);
        $this->assertEquals(0.0, $summary['outstanding_loan']);
    }

    public function test_a_non_counting_loan_does_not_touch_pending_until_settled(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-loan@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-loan@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $membership = $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'fixed_salary', 'salary_amount' => 2000, 'joined_at' => today()->toDateString(),
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 2000, 'method' => 'cash', 'entry_type' => 'loan',
        ])->assertCreated()
            ->assertJsonPath('summary.pending', 2000)
            ->assertJsonPath('summary.outstanding_loan', 2000);

        // Writing off more than is outstanding is rejected.
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/write-off", [
            'amount' => 2500,
        ])->assertUnprocessable()->assertJsonValidationErrors(['amount']);

        // A partial settlement reduces the outstanding balance without touching pending.
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/write-off", [
            'amount' => 800, 'notes' => 'Partly forgiven',
        ])->assertCreated()
            ->assertJsonPath('summary.outstanding_loan', 1200)
            ->assertJsonPath('summary.pending', 2000);
    }

    public function test_fixed_salary_payroll_reduces_net_profit_but_commission_payout_does_not_double_count(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-payroll@example.test', 'password' => 'password']);
        $salaried = User::query()->create(['name' => 'Salaried', 'email' => 'salaried@example.test', 'password' => 'password']);
        $commissioned = User::query()->create(['name' => 'Commissioned', 'email' => 'commissioned@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $salariedMembership = $business->memberships()->create([
            'user_id' => $salaried->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'fixed_salary', 'salary_amount' => 30000, 'joined_at' => today()->toDateString(),
        ]);
        $business->memberships()->create([
            'user_id' => $commissioned->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'commission', 'commission_rate' => 10,
        ]);
        $product = $business->products()->create([
            'name' => 'Service', 'type' => 'service', 'unit' => 'session', 'sale_price' => 1000, 'cost_price' => 0, 'tax_rate' => 0, 'active' => true,
        ]);

        app(\App\Services\InvoiceService::class)->create($business, $commissioned, [
            'customer_name' => 'Walk-in', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 1000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);

        $dashboard = app(DashboardService::class);
        $start = CarbonImmutable::today()->startOfMonth();
        $end = CarbonImmutable::today()->endOfMonth();

        // Before any salary is paid: only the accrued commission (100) reduces profit.
        $before = $dashboard->metrics($business->fresh(), $start, $end);
        $this->assertSame(0.0, $before['salary_cost']);
        $this->assertSame(100.0, $before['commissions']);
        $this->assertSame(900.0, $before['net_profit']);

        Sanctum::actingAs($owner);
        // Paying out that same commission must not reduce net profit again — it was
        // already recognized as an expense when the sale happened.
        $this->postJson("/api/businesses/{$business->id}/team/{$salariedMembership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 30000, 'method' => 'bank_transfer',
        ])->assertCreated();

        $after = $dashboard->metrics($business->fresh(), $start, $end);
        $this->assertSame(30000.0, $after['salary_cost']);
        $this->assertSame(100.0, $after['commissions']);
        $this->assertSame(-29100.0, $after['net_profit']);
    }

    public function test_writing_off_a_loan_reduces_net_profit_as_a_real_cost(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-writeoff@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-writeoff@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $membership = $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'commission', 'commission_rate' => 5,
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 1000, 'method' => 'cash', 'entry_type' => 'loan',
        ])->assertCreated();

        $dashboard = app(DashboardService::class);
        $start = CarbonImmutable::today()->startOfMonth();
        $end = CarbonImmutable::today()->endOfMonth();

        // A loan given but not yet forgiven is not a cost — it's still owed back.
        $this->assertSame(0.0, $dashboard->metrics($business->fresh(), $start, $end)['net_profit']);

        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/write-off", [
            'amount' => 1000,
        ])->assertCreated();

        // Once forgiven, it's a real loss.
        $this->assertSame(-1000.0, $dashboard->metrics($business->fresh(), $start, $end)['net_profit']);
    }

    public function test_only_team_manage_can_pay_or_write_off_salary(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-perm@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-perm@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $membership = $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true, 'pay_type' => 'commission',
        ]);

        Sanctum::actingAs($employee);
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 100, 'method' => 'cash',
        ])->assertForbidden();
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/write-off", [
            'amount' => 100,
        ])->assertForbidden();
    }
}
