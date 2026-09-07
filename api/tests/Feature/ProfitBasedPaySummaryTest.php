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

class ProfitBasedPaySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_profit_based_employees_pay_summary_uses_their_real_profit_share(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-pbp@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-pbp@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Profit Based Pay Co', 'slug' => 'profit-based-pay-co-'.uniqid(), 'code' => 'PB'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'PB', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'profit_share', 'salary_visible_to_staff' => true,
        ]);
        $product = $business->products()->create([
            'name' => 'Service', 'type' => 'service', 'unit' => 'session', 'sale_price' => 2000, 'cost_price' => 750, 'tax_rate' => 0, 'active' => true,
        ]);

        app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Walk-in', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 2000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);
        // net_profit = 2000 - 750 = 1250. Employee's profit share is 20% = 250.
        app(OwnershipService::class)->schedule($business, $employee->id, 0, 20, today()->toImmutable()->startOfDay(), null, $owner);

        Sanctum::actingAs($employee);
        $response = $this->getJson("/api/businesses/{$business->id}/my-salary")->assertOk()->json();

        $this->assertTrue($response['visible']);
        $this->assertSame('profit_share', $response['pay_type']);
        $this->assertEquals(250.0, $response['accrued']);
        $this->assertEquals(250.0, $response['pending']);
        $this->assertEquals(0.0, $response['paid_total']);
    }
}
