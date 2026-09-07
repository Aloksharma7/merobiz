<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AvailableBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_balance_is_a_running_cash_position_not_a_profit_figure(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-bal@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-bal@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Balance Co', 'slug' => 'balance-co-'.uniqid(), 'code' => 'BC'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'BC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true, 'commission_rate' => 0]);
        $membership = $business->memberships()->create([
            'user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true, 'pay_type' => 'commission', 'commission_rate' => 0,
        ]);
        $product = $business->products()->create([
            'name' => 'Service', 'type' => 'service', 'unit' => 'session', 'sale_price' => 10000, 'cost_price' => 4000, 'tax_rate' => 0, 'active' => true,
        ]);

        $invoice = app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Walk-in', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'Service', 'quantity' => 1, 'unit_price' => 10000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);

        Sanctum::actingAs($owner);
        // Sale collected in full: +10,000 cash in.
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice->id}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 10000, 'method' => 'bank_transfer',
        ])->assertCreated();
        // Hosting expense paid: -1,500.
        $business->expenses()->create([
            'submitted_by' => $owner->id, 'approved_by' => $owner->id, 'category' => 'Hosting', 'expense_date' => today()->toDateString(),
            'amount' => 1500, 'tax_amount' => 0, 'payment_method' => 'bank_transfer', 'status' => 'approved', 'approved_at' => now(),
        ]);
        // Employee salary paid: -2,000.
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 2000, 'method' => 'bank_transfer',
        ])->assertCreated();
        // Employee given a loan: -500 (real cash out, but not an expense).
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 500, 'method' => 'cash', 'entry_type' => 'loan',
        ])->assertCreated();
        // Owner withdraws 1,000 of their earned profit share: -1,000 (a distribution, not an expense).
        $this->postJson("/api/businesses/{$business->id}/profit-withdrawals", [
            'withdrawn_on' => today()->toDateString(), 'amount' => 1000,
        ])->assertCreated();

        $dashboard = app(DashboardService::class);
        $balance = $dashboard->availableBalance($business->fresh());

        $this->assertSame(10000.0, $balance['collected']);
        $this->assertSame(1500.0, $balance['expenses_paid']);
        // Payroll here includes the loan (real cash out) — 2000 salary + 500 loan.
        $this->assertSame(2500.0, $balance['payroll_paid']);
        $this->assertSame(1000.0, $balance['owner_withdrawn']);
        $this->assertSame(5000.0, $balance['available_balance']);

        // Net profit is a different question and gives a different, still-correct answer:
        // gross profit (10000 - 4000 cost) minus expenses (1500) minus payroll (2500,
        // loan included since it's not yet written off... actually loan is excluded from
        // payrollCost() until written off, so net profit only subtracts the 2000 salary).
        $metrics = $dashboard->metrics($business->fresh(), today()->startOfMonth(), today()->endOfMonth());
        $this->assertSame(2500.0, $metrics['net_profit']);
    }
}
