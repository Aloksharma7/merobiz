<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashBasisProfitTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Cash Basis Co', 'slug' => 'cash-basis-co-'.uniqid(), 'code' => 'CB'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'CB', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_an_unpaid_sale_recognizes_no_profit_yet(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-cb1@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $product = $business->products()->create([
            'name' => 'Service', 'type' => 'service', 'unit' => 'session', 'sale_price' => 2000, 'cost_price' => 1000, 'tax_rate' => 0, 'active' => true,
        ]);
        app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Walk-in', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 2000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);

        $metrics = app(DashboardService::class)->metrics($business, CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today());

        $this->assertEquals(2000.0, $metrics['net_sales']);
        $this->assertEquals(0.0, $metrics['gross_profit']);
        $this->assertEquals(0.0, $metrics['net_profit']);
    }

    public function test_a_partially_paid_sale_recognizes_profit_proportional_to_what_is_collected(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-cb2@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $product = $business->products()->create([
            'name' => 'Service', 'type' => 'service', 'unit' => 'session', 'sale_price' => 2000, 'cost_price' => 1000, 'tax_rate' => 0, 'active' => true,
        ]);
        $invoice = app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Walk-in', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 2000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);
        // 60% collected (1200 of 2000).
        app(PaymentService::class)->record($business, $invoice, $owner, [
            'payment_date' => today()->toDateString(), 'amount' => 1200, 'method' => 'bank_transfer',
        ]);

        $metrics = app(DashboardService::class)->metrics($business, CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today());

        // Full profit would be 2000 - 1000 = 1000. 60% collected -> 600 recognized.
        $this->assertEquals(2000.0, $metrics['net_sales']);
        $this->assertEquals(600.0, $metrics['gross_profit']);
        $this->assertEquals(600.0, $metrics['net_profit']);
    }

    public function test_a_fully_paid_sale_recognizes_the_full_profit(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-cb3@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $product = $business->products()->create([
            'name' => 'Service', 'type' => 'service', 'unit' => 'session', 'sale_price' => 2000, 'cost_price' => 1000, 'tax_rate' => 0, 'active' => true,
        ]);
        $invoice = app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Walk-in', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 2000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);
        app(PaymentService::class)->record($business, $invoice, $owner, [
            'payment_date' => today()->toDateString(), 'amount' => 2000, 'method' => 'bank_transfer',
        ]);

        $metrics = app(DashboardService::class)->metrics($business, CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today());

        $this->assertEquals(1000.0, $metrics['gross_profit']);
        $this->assertEquals(1000.0, $metrics['net_profit']);
    }
}
