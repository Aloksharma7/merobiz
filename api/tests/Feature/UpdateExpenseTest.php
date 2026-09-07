<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateExpenseTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner, string $category = 'standard'): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Edit Expense Co', 'slug' => 'edit-expense-co-'.uniqid(), 'code' => 'EE'.rand(1000, 9999),
            'business_type' => 'service', 'category' => $category, 'currency' => 'NPR', 'invoice_prefix' => 'EE', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_an_admin_can_edit_an_expense(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-ue1@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Cloud services', 'expense_date' => today()->toDateString(), 'amount' => 500,
            'payment_method' => 'bank_transfer',
        ])->assertCreated();
        $expense = Expense::query()->latest('id')->first();

        $this->patchJson("/api/businesses/{$business->id}/expenses/{$expense->id}", [
            'category' => 'Cloud services', 'expense_date' => today()->toDateString(), 'amount' => 750,
            'payment_method' => 'cash',
        ])->assertOk()->assertJsonPath('expense.amount', 750)->assertJsonPath('expense.payment_method', 'cash');

        $this->assertEquals(750.0, (float) $expense->fresh()->amount);
    }

    public function test_an_employee_cannot_edit_someone_elses_expense(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-ue2@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-ue2@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Cloud services', 'expense_date' => today()->toDateString(), 'amount' => 500,
            'payment_method' => 'bank_transfer',
        ])->assertCreated();
        $expense = Expense::query()->latest('id')->first();

        Sanctum::actingAs($employee);
        $this->patchJson("/api/businesses/{$business->id}/expenses/{$expense->id}", [
            'category' => 'Cloud services', 'expense_date' => today()->toDateString(), 'amount' => 999,
            'payment_method' => 'cash',
        ])->assertForbidden();
        $this->assertEquals(500.0, (float) $expense->fresh()->amount);
    }

    public function test_installment_businesses_cannot_mark_an_expense_as_already_priced_in(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-ue3@example.test', 'password' => 'password']);
        $business = $this->business($owner, 'installment');
        Sanctum::actingAs($owner);

        // There is no automatic cost-of-sales figure for installment/thesis
        // businesses to cap this exemption against (see DashboardService::metrics(),
        // $cost is always 0 there) — the flag would silently do nothing, so the
        // server ignores a false value and always stores it as true.
        $response = $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Printing', 'expense_date' => today()->toDateString(), 'amount' => 500,
            'payment_method' => 'cash', 'affects_profit' => false,
        ])->assertCreated();
        $this->assertTrue($response->json('expense.affects_profit'));

        $expense = Expense::query()->latest('id')->first();
        $updateResponse = $this->patchJson("/api/businesses/{$business->id}/expenses/{$expense->id}", [
            'category' => 'Printing', 'expense_date' => today()->toDateString(), 'amount' => 500,
            'payment_method' => 'cash', 'affects_profit' => false,
        ])->assertOk();
        $this->assertTrue($updateResponse->json('expense.affects_profit'));
    }
}
