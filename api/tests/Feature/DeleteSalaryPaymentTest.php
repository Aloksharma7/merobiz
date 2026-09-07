<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteSalaryPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_delete_an_accidentally_recorded_payroll_payment(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dsp@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-dsp@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Delete Payment Co', 'slug' => 'delete-payment-co-'.uniqid(), 'code' => 'DP'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'DP', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $membership = $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'fixed_salary', 'salary_amount' => 30000, 'joined_at' => today()->toDateString(),
        ]);

        Sanctum::actingAs($owner);
        $payResponse = $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 30000, 'method' => 'bank_transfer',
        ])->assertCreated();
        $this->assertEquals(0.0, $payResponse->json('summary.pending'));

        $paymentId = $membership->salaryPayments()->latest('id')->first()->id;

        $deleteResponse = $this->deleteJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments/{$paymentId}")
            ->assertOk();
        // Deleting the payment un-does it — pending goes back to the full accrued amount.
        $this->assertEquals(30000.0, $deleteResponse->json('summary.pending'));

        $this->assertDatabaseMissing('salary_payments', ['id' => $paymentId]);
    }

    public function test_an_employee_cannot_delete_a_payroll_payment(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dsp2@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-dsp2@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Delete Payment Guard Co', 'slug' => 'delete-payment-guard-co-'.uniqid(), 'code' => 'DG'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'DG', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $membership = $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'fixed_salary', 'salary_amount' => 30000, 'joined_at' => today()->toDateString(),
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 30000, 'method' => 'bank_transfer',
        ])->assertCreated();
        $paymentId = $membership->salaryPayments()->latest('id')->first()->id;

        Sanctum::actingAs($employee);
        $this->deleteJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments/{$paymentId}")
            ->assertForbidden();
        $this->assertDatabaseHas('salary_payments', ['id' => $paymentId]);
    }
}
