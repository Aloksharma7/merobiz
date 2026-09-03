<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_auth_and_navigation_are_bound_to_one_business_workspace(): void
    {
        $owner = $this->user('Owner', 'owner-workspace@example.test');
        $employee = $this->user('Employee', 'employee-workspace@example.test');
        $business = $this->business($owner, 'AimersZone', 'AIM');
        $business->memberships()->create([
            'user_id' => $owner->id,
            'role' => BusinessRole::Owner,
            'active' => true,
        ]);
        $business->memberships()->create([
            'user_id' => $employee->id,
            'role' => BusinessRole::Employee,
            'active' => true,
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('workspace.mode', 'employee')
            ->assertJsonPath('workspace.business_id', $business->id)
            ->assertJsonPath('workspace.business_name', 'AimersZone');

        $this->getJson('/api/portfolio/dashboard')->assertForbidden();
        $this->getJson("/api/businesses/{$business->id}/team")->assertForbidden();
        $this->getJson("/api/businesses/{$business->id}/expenses")->assertForbidden();
        $this->getJson("/api/businesses/{$business->id}/reports/profit-loss")->assertForbidden();
    }

    public function test_employee_dashboard_invoices_and_customer_balance_only_use_their_own_sales(): void
    {
        $owner = $this->user('Owner', 'owner-sales@example.test');
        $employee = $this->user('Employee One', 'employee-one@example.test');
        $otherEmployee = $this->user('Employee Two', 'employee-two@example.test');
        $business = $this->business($owner, 'AimersZone', 'AIM2');
        $business->memberships()->createMany([
            ['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true],
            ['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'commission_rate' => 5, 'active' => true],
            ['user_id' => $otherEmployee->id, 'role' => BusinessRole::Employee, 'commission_rate' => 5, 'active' => true],
        ]);
        $customer = $business->customers()->create(['name' => 'Shared Customer', 'active' => true]);
        $product = $business->products()->create([
            'name' => 'AI Tool',
            'type' => 'digital_subscription',
            'unit' => 'license',
            'sale_price' => 1000,
            'cost_price' => 500,
            'tax_rate' => 0,
            'active' => true,
        ]);

        $this->invoice($business, $employee, $customer->id, $product->id, 1000);
        $this->invoice($business, $otherEmployee, $customer->id, $product->id, 2500);

        Sanctum::actingAs($employee);

        $this->getJson("/api/businesses/{$business->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('mode', 'personal')
            ->assertJsonPath('summary.net_sales', 1000)
            ->assertJsonPath('summary.invoice_count', 1)
            ->assertJsonCount(1, 'recent_invoices')
            ->assertJsonPath('recent_invoices.0.seller_name', 'Employee One');

        $this->getJson("/api/businesses/{$business->id}/invoices")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.creator.name', 'Employee One');

        $this->getJson("/api/businesses/{$business->id}/customers")
            ->assertOk()
            ->assertJsonPath('data.0.outstanding_balance', 1000);
    }

    public function test_employee_cannot_be_assigned_to_two_active_businesses(): void
    {
        $owner = $this->user('Owner', 'owner-team@example.test');
        $employee = $this->user('Employee', 'single-company@example.test');
        $first = $this->business($owner, 'AimersZone', 'AIM3');
        $second = $this->business($owner, 'TechChamp', 'TECH');
        foreach ([$first, $second] as $business) {
            $business->memberships()->create([
                'user_id' => $owner->id,
                'role' => BusinessRole::Owner,
                'active' => true,
            ]);
        }
        $first->memberships()->create([
            'user_id' => $employee->id,
            'role' => BusinessRole::Employee,
            'active' => true,
        ]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/businesses/{$second->id}/team", [
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => 'employee',
            'commission_rate' => 0,
        ])->assertUnprocessable();
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

    private function business(User $owner, string $name, string $code): Business
    {
        return Business::query()->create([
            'owner_id' => $owner->id,
            'name' => $name,
            'slug' => strtolower($code),
            'code' => $code,
            'business_type' => 'digital_subscription',
            'currency' => 'NPR',
            'invoice_prefix' => $code,
            'default_tax_rate' => 0,
            'status' => 'active',
        ]);
    }

    private function invoice(Business $business, User $seller, int $customerId, int $productId, int $price): void
    {
        app(InvoiceService::class)->create($business, $seller, [
            'customer_id' => $customerId,
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [[
                'product_id' => $productId,
                'description' => 'AI Tool',
                'quantity' => 1,
                'unit_price' => $price,
                'discount_amount' => 0,
                'tax_rate' => 0,
            ]],
        ]);
    }
}
