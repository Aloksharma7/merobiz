<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Sample Shop', 'slug' => 'sample-shop-'.uniqid(), 'code' => 'SHP'.rand(1000, 9999),
            'business_type' => 'product', 'currency' => 'NPR', 'invoice_prefix' => 'SHP',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true]);

        return $business;
    }

    public function test_commission_is_earned_on_gross_profit_not_on_the_full_sale_amount(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $staff = User::query()->create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $business->memberships()->create(['user_id' => $staff->id, 'role' => BusinessRole::Employee, 'commission_rate' => 25, 'active' => true]);

        $product = $business->products()->create([
            'name' => 'Widget', 'type' => 'product', 'unit' => 'pcs',
            'sale_price' => 3400, 'cost_price' => 3000, 'tax_rate' => 0, 'active' => true,
        ]);

        $invoice = app(InvoiceService::class)->create($business, $staff, [
            'customer_name' => 'Walk-in',
            'invoice_date' => today()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [[
                'product_id' => $product->id, 'description' => 'Widget', 'quantity' => 1, 'unit_price' => 3400,
                'discount_amount' => 0, 'tax_rate' => 0,
            ]],
        ]);

        // Sold at 3400, cost 3000 -> gross profit 400. At 25% commission that's 100,
        // not the 850 you'd get from taking 25% of the full 3400 sale price.
        $this->assertSame('3000.00', (string) $invoice->cost_amount);
        $this->assertEquals(100.0, (float) $invoice->commission_amount);
    }

    public function test_a_sale_at_or_below_cost_earns_no_commission(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2@example.test', 'password' => 'password']);
        $staff = User::query()->create(['name' => 'Staff', 'email' => 'staff2@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $business->memberships()->create(['user_id' => $staff->id, 'role' => BusinessRole::Employee, 'commission_rate' => 25, 'active' => true]);

        $product = $business->products()->create([
            'name' => 'Clearance item', 'type' => 'product', 'unit' => 'pcs',
            'sale_price' => 1000, 'cost_price' => 1200, 'tax_rate' => 0, 'active' => true,
        ]);

        $invoice = app(InvoiceService::class)->create($business, $staff, [
            'customer_name' => 'Walk-in',
            'invoice_date' => today()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [[
                'product_id' => $product->id, 'description' => 'Clearance item', 'quantity' => 1, 'unit_price' => 1000,
                'discount_amount' => 0, 'tax_rate' => 0,
            ]],
        ]);

        // Sold below cost -> no commission, never a negative one.
        $this->assertEquals(0.0, (float) $invoice->commission_amount);
    }
}
