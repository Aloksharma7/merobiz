<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeExpensesAndExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_submit_an_expense_that_is_auto_approved_and_only_sees_their_own(): void
    {
        $owner = $this->user('Owner', 'owner-exp@example.test');
        $employee = $this->user('Employee', 'employee-exp@example.test');
        $business = $this->business($owner);
        $business->memberships()->createMany([
            ['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true],
            ['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true],
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Office supplies',
            'expense_date' => today()->toDateString(),
            'amount' => 500,
            'payment_method' => 'cash',
        ])->assertCreated()->assertJsonPath('expense.status', 'approved');

        Sanctum::actingAs($employee);
        $response = $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Travel',
            'expense_date' => today()->toDateString(),
            'amount' => 250,
            'payment_method' => 'cash',
        ])->assertCreated();
        $response->assertJsonPath('expense.status', 'approved');
        $expenseId = $response->json('expense.id');

        $this->getJson("/api/businesses/{$business->id}/expenses")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'Travel');

        $this->patchJson("/api/businesses/{$business->id}/expenses/{$expenseId}/status", ['status' => 'rejected'])
            ->assertForbidden();

        // An employee's own expense is already approved, so they can no longer delete it themselves.
        $this->deleteJson("/api/businesses/{$business->id}/expenses/{$expenseId}")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/businesses/{$business->id}/expenses/{$expenseId}")->assertOk();
    }

    public function test_only_admins_can_export_sales_and_expenses(): void
    {
        $owner = $this->user('Owner', 'owner-export@example.test');
        $employee = $this->user('Employee', 'employee-export@example.test');
        $business = $this->business($owner);
        $business->memberships()->createMany([
            ['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true],
            ['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true],
        ]);

        Sanctum::actingAs($employee);
        $this->get("/api/businesses/{$business->id}/invoices/export")->assertForbidden();
        $this->get("/api/businesses/{$business->id}/expenses/export")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->get("/api/businesses/{$business->id}/invoices/export")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get("/api/businesses/{$business->id}/expenses/export")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function user(string $name, string $email): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password123',
            'preferred_currency' => 'NPR',
        ]);
    }

    private function business(User $owner): Business
    {
        return Business::query()->create([
            'owner_id' => $owner->id,
            'name' => 'AimersZone',
            'slug' => 'aimerszone-'.uniqid(),
            'code' => 'AIM',
            'business_type' => 'digital_subscription',
            'currency' => 'NPR',
            'invoice_prefix' => 'AIM',
            'default_tax_rate' => 0,
            'status' => 'active',
        ]);
    }
}
