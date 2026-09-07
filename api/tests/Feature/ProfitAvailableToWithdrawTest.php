<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\OwnershipService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfitAvailableToWithdrawTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawing_exactly_what_you_earned_zeroes_out_what_remains_but_not_lifetime_earned(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-patw@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Withdraw Clarity Co', 'slug' => 'withdraw-clarity-co-'.uniqid(), 'code' => 'WC'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'WC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        app(OwnershipService::class)->schedule($business, $owner->id, 80, 80, today()->toImmutable()->startOfDay(), null, $owner);

        $product = $business->products()->create([
            'name' => 'AI check', 'type' => 'service', 'unit' => 'session', 'sale_price' => 2000, 'cost_price' => 750, 'tax_rate' => 0, 'active' => true,
        ]);
        $invoice = app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Walk-in', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'AI check', 'quantity' => 1, 'unit_price' => 2000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);
        app(PaymentService::class)->record($business, $invoice, $owner, [
            'payment_date' => today()->toDateString(), 'amount' => 2000, 'method' => 'bank_transfer',
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Boosting', 'expense_date' => today()->toDateString(), 'amount' => 750, 'payment_method' => 'bank_transfer',
        ])->assertCreated();
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'AI subscription', 'expense_date' => today()->toDateString(), 'amount' => 750,
            'payment_method' => 'bank_transfer', 'affects_profit' => false,
        ])->assertCreated();

        $range = 'start='.today()->startOfMonth()->toDateString().'&end='.today()->toDateString();

        // net_profit = 500, 80% share = 400 earned. Available balance = 2000 - 1500 = 500.
        $before = $this->getJson("/api/businesses/{$business->id}/dashboard?{$range}")->assertOk()->json();
        $this->assertEquals(400.0, $before['summary']['attributable_profit']);
        $this->assertEquals(400.0, $before['summary']['lifetime_profit_earned']);
        $this->assertEquals(0.0, $before['summary']['lifetime_profit_withdrawn']);
        $this->assertEquals(400.0, $before['summary']['profit_available_to_withdraw']);
        $this->assertEquals(500.0, $before['available_balance']['available_balance']);

        $this->postJson("/api/businesses/{$business->id}/profit-withdrawals", [
            'withdrawn_on' => today()->toDateString(), 'amount' => 400,
        ])->assertCreated();

        $after = $this->getJson("/api/businesses/{$business->id}/dashboard?{$range}")->assertOk()->json();
        // Earned profit is unchanged — you still earned it, taking it out doesn't erase that.
        $this->assertEquals(400.0, $after['summary']['attributable_profit']);
        $this->assertEquals(400.0, $after['summary']['lifetime_profit_earned']);
        // But now it's clear you've already taken all of it, nothing left to withdraw.
        $this->assertEquals(400.0, $after['summary']['lifetime_profit_withdrawn']);
        $this->assertEquals(0.0, $after['summary']['profit_available_to_withdraw']);
        $this->assertEquals(100.0, $after['available_balance']['available_balance']);
    }
}
