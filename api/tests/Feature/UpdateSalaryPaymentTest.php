<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateSalaryPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeBusiness(): array
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-usp-'.uniqid().'@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-usp-'.uniqid().'@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Edit Payment Co', 'slug' => 'edit-payment-co-'.uniqid(), 'code' => 'EP'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'EP', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $membership = $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'fixed_salary', 'salary_amount' => 30000, 'joined_at' => today()->toDateString(),
        ]);

        return [$owner, $employee, $business, $membership];
    }

    public function test_an_admin_can_edit_a_payments_amount(): void
    {
        [$owner, , $business, $membership] = $this->makeBusiness();

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 10000, 'method' => 'bank_transfer',
        ])->assertCreated();
        $paymentId = $membership->salaryPayments()->latest('id')->first()->id;

        $response = $this->patchJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments/{$paymentId}", [
            'payment_date' => today()->toDateString(), 'amount' => 15000, 'method' => 'cash',
        ])->assertOk();
        // Pending was 30000 - 10000 = 20000, now 30000 - 15000 = 15000.
        $this->assertEquals(15000.0, $response->json('summary.pending'));

        $this->assertDatabaseHas('salary_payments', ['id' => $paymentId, 'amount' => 15000, 'method' => 'cash']);
    }

    public function test_an_employee_cannot_edit_a_payroll_payment(): void
    {
        [$owner, $employee, $business, $membership] = $this->makeBusiness();

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 10000, 'method' => 'bank_transfer',
        ])->assertCreated();
        $paymentId = $membership->salaryPayments()->latest('id')->first()->id;

        Sanctum::actingAs($employee);
        $this->patchJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments/{$paymentId}", [
            'payment_date' => today()->toDateString(), 'amount' => 20000, 'method' => 'cash',
        ])->assertForbidden();
        $this->assertDatabaseHas('salary_payments', ['id' => $paymentId, 'amount' => 10000]);
    }

    public function test_a_settled_write_off_cannot_be_edited(): void
    {
        [$owner, , $business, $membership] = $this->makeBusiness();

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 5000, 'method' => 'cash', 'entry_type' => 'loan',
        ])->assertCreated();
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/write-off", [
            'amount' => 5000,
        ])->assertCreated();
        $writeOffId = $membership->salaryPayments()->where('entry_type', 'write_off')->latest('id')->first()->id;

        $this->patchJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments/{$writeOffId}", [
            'payment_date' => today()->toDateString(), 'amount' => 4000, 'method' => 'cash',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('salary_payments', ['id' => $writeOffId, 'amount' => 5000]);
    }
}
