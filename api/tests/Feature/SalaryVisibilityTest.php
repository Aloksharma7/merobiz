<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalaryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_salary_employee_only_sees_their_own_salary_after_admin_makes_it_visible(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $employeeA = User::query()->create(['name' => 'Employee A', 'email' => 'a@example.test', 'password' => 'password']);
        $employeeB = User::query()->create(['name' => 'Employee B', 'email' => 'b@example.test', 'password' => 'password']);

        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Enlighten Research', 'slug' => 'enlighten', 'code' => 'ERC',
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'ERC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true]);
        $membershipA = $business->memberships()->create([
            'user_id' => $employeeA->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'fixed_salary', 'salary_amount' => 30000, 'salary_visible_to_staff' => false,
            'joined_at' => today()->subMonths(2)->toDateString(),
        ]);
        $business->memberships()->create([
            'user_id' => $employeeB->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'commission', 'commission_rate' => 5,
        ]);

        // Not yet visible: employee A sees nothing.
        Sanctum::actingAs($employeeA);
        $this->getJson("/api/businesses/{$business->id}/my-salary")
            ->assertOk()
            ->assertJson(['visible' => false])
            ->assertJsonMissingPath('pending');

        // Admin (owner here) turns visibility on.
        Sanctum::actingAs($owner);
        $this->patchJson("/api/businesses/{$business->id}/team/{$membershipA->id}", [
            'salary_visible_to_staff' => true,
        ])->assertOk();

        Sanctum::actingAs($employeeA);
        $response = $this->getJson("/api/businesses/{$business->id}/my-salary")->assertOk();
        $response->assertJsonPath('visible', true);
        $response->assertJsonPath('salary_amount', 30000);
        // 3 months elapsed (joined 2 months ago, inclusive of the current month) x 30000 = 90000 pending, nothing paid yet.
        $response->assertJsonPath('pending', 90000);

        // Commission-based employee B sees nothing until an admin flips their visibility on too.
        Sanctum::actingAs($employeeB);
        $this->getJson("/api/businesses/{$business->id}/my-salary")
            ->assertOk()
            ->assertJson(['visible' => false]);
    }

    public function test_commission_employee_sees_lifetime_commission_owed_once_visible(): void
    {
        $owner = $this->user('Owner', 'owner-commission@example.test');
        $employee = $this->user('Employee', 'employee-commission@example.test');
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'AimersZone', 'slug' => 'aimerszone-commission', 'code' => 'AIMC',
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'AIMC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true]);
        $membership = $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'commission', 'commission_rate' => 10, 'salary_visible_to_staff' => false,
        ]);
        $product = $business->products()->create([
            'name' => 'Consulting', 'type' => 'service', 'unit' => 'session',
            'sale_price' => 1000, 'cost_price' => 0, 'tax_rate' => 0, 'active' => true,
        ]);

        Sanctum::actingAs($employee);
        $this->getJson("/api/businesses/{$business->id}/my-salary")->assertOk()->assertJson(['visible' => false]);

        app(\App\Services\InvoiceService::class)->create($business, $employee, [
            'customer_name' => 'Walk-in',
            'invoice_date' => today()->toDateString(),
            'status' => 'issued',
            'discount_amount' => 0,
            'items' => [[
                'product_id' => $product->id, 'description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000,
                'discount_amount' => 0, 'tax_rate' => 0,
            ]],
        ]);

        Sanctum::actingAs($owner);
        $this->patchJson("/api/businesses/{$business->id}/team/{$membership->id}", ['salary_visible_to_staff' => true])->assertOk();

        Sanctum::actingAs($employee);
        $this->getJson("/api/businesses/{$business->id}/my-salary")
            ->assertOk()
            ->assertJsonPath('visible', true)
            ->assertJsonPath('pay_type', 'commission')
            ->assertJsonPath('pending', 100);
    }

    public function test_an_employee_sees_their_own_payment_history_once_visible_but_not_before(): void
    {
        $owner = $this->user('Owner', 'owner-history@example.test');
        $employee = $this->user('Employee', 'employee-history@example.test');
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Enlighten Research', 'slug' => 'enlighten-history', 'code' => 'ERH',
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'ERH', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $membership = $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'fixed_salary', 'salary_amount' => 20000, 'salary_visible_to_staff' => false,
            'joined_at' => today()->toDateString(),
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 5000, 'method' => 'cash', 'notes' => 'First payout',
        ])->assertCreated();

        // Not visible yet: no payment history leaks even though a payment exists.
        Sanctum::actingAs($employee);
        $this->getJson("/api/businesses/{$business->id}/my-salary")
            ->assertOk()
            ->assertJson(['visible' => false])
            ->assertJsonMissingPath('payments');

        Sanctum::actingAs($owner);
        $this->patchJson("/api/businesses/{$business->id}/team/{$membership->id}", ['salary_visible_to_staff' => true])->assertOk();

        Sanctum::actingAs($employee);
        $response = $this->getJson("/api/businesses/{$business->id}/my-salary")->assertOk();
        $response->assertJsonPath('visible', true);
        $response->assertJsonCount(1, 'payments');
        $response->assertJsonPath('payments.0.amount', 5000);
        $response->assertJsonPath('payments.0.notes', 'First payout');
        $response->assertJsonPath('payments.0.recorded_by', 'Owner');
    }

    private function user(string $name, string $email): User
    {
        return User::query()->create(['name' => $name, 'email' => $email, 'password' => 'password123']);
    }
}
