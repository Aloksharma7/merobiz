<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_net_profit_and_owner_attribution_are_calculated_separately(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $seller = User::query()->create(['name' => 'Seller', 'email' => 'seller@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id,
            'name' => 'AI Tools',
            'slug' => 'ai-tools',
            'code' => 'AIT',
            'business_type' => 'digital_subscription',
            'currency' => 'NPR',
            'invoice_prefix' => 'AIT',
            'default_tax_rate' => 0,
            'status' => 'active',
        ]);
        $business->memberships()->createMany([
            ['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'commission_rate' => 0, 'active' => true],
            ['user_id' => $seller->id, 'role' => BusinessRole::Employee, 'commission_rate' => 10, 'active' => true],
        ]);
        $business->ownerships()->create([
            'user_id' => $owner->id,
            'ownership_percent' => 40,
            'profit_share_percent' => 40,
            'effective_from' => today()->subYear()->toDateString(),
        ]);
        $product = $business->products()->create([
            'name' => 'AI subscription',
            'type' => 'digital_subscription',
            'unit' => 'account',
            'sale_price' => 1000,
            'cost_price' => 600,
            'tax_rate' => 0,
            'active' => true,
        ]);

        $invoice = app(InvoiceService::class)->create($business, $seller, [
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => 1,
                'unit_price' => 1000,
                'unit_cost' => 600,
                'discount_amount' => 0,
                'tax_rate' => 0,
            ]],
        ]);
        // Employee sales affect business profit immediately; there is no approval step.

        $business->expenses()->create([
            'submitted_by' => $owner->id,
            'approved_by' => $owner->id,
            'category' => 'Hosting',
            'expense_date' => today()->toDateString(),
            'amount' => 100,
            'tax_amount' => 0,
            'payment_method' => PaymentMethod::BankTransfer,
            'status' => ExpenseStatus::Approved,
            'approved_at' => now(),
        ]);

        $dashboard = app(DashboardService::class);
        $start = CarbonImmutable::today()->startOfDay();
        $end = CarbonImmutable::today()->endOfDay();
        $metrics = $dashboard->metrics($business, $start, $end);

        $this->assertSame(1000.0, $metrics['net_sales']);
        $this->assertSame(600.0, $metrics['cost_of_sales']);
        // Commission is earned on gross profit (1000 - 600 cost = 400), not on the full sale.
        $this->assertSame(40.0, $metrics['commissions']);
        $this->assertSame(100.0, $metrics['expenses']);
        $this->assertSame(260.0, $metrics['net_profit']);
        $this->assertSame(104.0, $dashboard->attributableProfit($owner, $business, $start, $end));
    }
}
