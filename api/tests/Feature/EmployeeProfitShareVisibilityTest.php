<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\OwnershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeProfitShareVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_employee_sees_their_own_profit_share_amount_and_the_admin_sees_it_too(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-epsv@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-epsv@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Profit Visibility Co', 'slug' => 'profit-visibility-co-'.uniqid(), 'code' => 'PV'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'PV', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true, 'commission_rate' => 0]);
        $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true, 'commission_rate' => 0]);
        $product = $business->products()->create([
            'name' => 'Service', 'type' => 'service', 'unit' => 'session', 'sale_price' => 2000, 'cost_price' => 750, 'tax_rate' => 0, 'active' => true,
        ]);

        app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Walk-in', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 2000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);
        // net_profit = 2000 - 750 = 1250. Owner 80% = 1000. Employee 20% = 250.
        app(OwnershipService::class)->schedule($business, $owner->id, 80, 80, today()->toImmutable()->startOfDay(), null, $owner);
        app(OwnershipService::class)->schedule($business, $employee->id, 0, 20, today()->toImmutable()->startOfDay(), null, $owner);

        $range = 'start='.today()->startOfMonth()->toDateString().'&end='.today()->toDateString();

        Sanctum::actingAs($owner);
        $ownerDashboard = $this->getJson("/api/businesses/{$business->id}/dashboard?{$range}")->assertOk()->json();
        $this->assertEquals(1000.0, $ownerDashboard['summary']['attributable_profit']);

        $team = $this->getJson("/api/businesses/{$business->id}/team?{$range}")->assertOk()->json('data');
        $employeeRow = collect($team)->firstWhere('user_id', $employee->id);
        $this->assertEquals(20.0, $employeeRow['profit_share_percent']);
        $this->assertEquals(250.0, $employeeRow['profit_earned']);

        Sanctum::actingAs($employee);
        $employeeDashboard = $this->getJson("/api/businesses/{$business->id}/dashboard?{$range}")->assertOk()->json();
        // This is the bug: before the fix this was forced to 0.0 for anyone without
        // dashboard.financial, even though it's only ever their own share, never the
        // business's overall figures.
        $this->assertEquals(250.0, $employeeDashboard['summary']['attributable_profit']);
        $this->assertEquals(20.0, $employeeDashboard['business']['profit_share_percent']);
        // Net profit itself must still stay hidden from the employee.
        $this->assertEquals(0.0, $employeeDashboard['summary']['net_profit']);
    }
}
