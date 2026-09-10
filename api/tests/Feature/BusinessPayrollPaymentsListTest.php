<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessPayrollPaymentsListTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_list_every_payroll_payment_across_the_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-ppl1@example.test', 'password' => 'password']);
        $employeeOne = User::query()->create(['name' => 'Employee One', 'email' => 'employee-ppl1a@example.test', 'password' => 'password']);
        $employeeTwo = User::query()->create(['name' => 'Employee Two', 'email' => 'employee-ppl1b@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Payroll List Co', 'slug' => 'payroll-list-co-'.uniqid(), 'code' => 'PL'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'PL', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $membershipOne = $business->memberships()->create([
            'user_id' => $employeeOne->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'fixed_salary', 'salary_amount' => 20000, 'joined_at' => today()->toDateString(),
        ]);
        $membershipTwo = $business->memberships()->create([
            'user_id' => $employeeTwo->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'fixed_salary', 'salary_amount' => 25000, 'joined_at' => today()->toDateString(),
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/team/{$membershipOne->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 10000, 'method' => 'bank_transfer',
        ])->assertCreated();
        $this->postJson("/api/businesses/{$business->id}/team/{$membershipTwo->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 12000, 'method' => 'cash',
        ])->assertCreated();

        $response = $this->getJson("/api/businesses/{$business->id}/payroll-payments")->assertOk();
        $names = collect($response->json('data'))->pluck('employee_name')->all();
        $this->assertCount(2, $response->json('data'));
        $this->assertContains('Employee One', $names);
        $this->assertContains('Employee Two', $names);
    }

    public function test_an_employee_cannot_list_business_wide_payroll_payments(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-ppl2@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-ppl2@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Payroll List Guard Co', 'slug' => 'payroll-list-guard-co-'.uniqid(), 'code' => 'PG'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'PG', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true]);

        Sanctum::actingAs($employee);
        $this->getJson("/api/businesses/{$business->id}/payroll-payments")->assertForbidden();
    }
}
