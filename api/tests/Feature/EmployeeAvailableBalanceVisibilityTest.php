<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeAvailableBalanceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner, string $category): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Balance Visibility Co', 'slug' => 'balance-visibility-co-'.uniqid(), 'code' => 'BV'.rand(1000, 9999),
            'business_type' => 'service', 'category' => $category, 'currency' => 'NPR', 'invoice_prefix' => 'BVC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_an_employee_sees_available_balance_in_a_standard_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-eabv1@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-eabv1@example.test', 'password' => 'password']);
        $business = $this->business($owner, 'standard');
        $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Rent', 'expense_date' => today()->toDateString(), 'amount' => 1000, 'payment_method' => 'cash',
        ])->assertCreated();

        Sanctum::actingAs($employee);
        $this->getJson("/api/businesses/{$business->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('mode', 'personal')
            ->assertJsonPath('available_balance.expenses_paid', 1000);
    }

    public function test_an_employee_does_not_see_available_balance_in_an_installment_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-eabv2@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-eabv2@example.test', 'password' => 'password']);
        $business = $this->business($owner, 'installment');
        $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Printing', 'expense_date' => today()->toDateString(), 'amount' => 1000, 'payment_method' => 'cash',
        ])->assertCreated();

        Sanctum::actingAs($employee);
        $this->getJson("/api/businesses/{$business->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('mode', 'personal')
            ->assertJsonPath('available_balance.expenses_paid', 0)
            ->assertJsonPath('available_balance.available_balance', 0);
    }

    public function test_an_admin_still_sees_available_balance_in_an_installment_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-eabv3@example.test', 'password' => 'password']);
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin-eabv3@example.test', 'password' => 'password']);
        $business = $this->business($owner, 'installment');
        $business->memberships()->create(['user_id' => $admin->id, 'role' => BusinessRole::Admin, 'active' => true]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Printing', 'expense_date' => today()->toDateString(), 'amount' => 1000, 'payment_method' => 'cash',
        ])->assertCreated();

        Sanctum::actingAs($admin);
        $this->getJson("/api/businesses/{$business->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('available_balance.expenses_paid', 1000);
    }
}
