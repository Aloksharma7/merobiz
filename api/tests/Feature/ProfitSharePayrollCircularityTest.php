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

class ProfitSharePayrollCircularityTest extends TestCase
{
    use RefreshDatabase;

    public function test_paying_a_profit_share_members_exact_accrued_amount_never_shows_as_overpaid(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-pspc@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Payroll Circularity Co', 'slug' => 'payroll-circularity-co-'.uniqid(), 'code' => 'PC'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'PC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $membership = $business->memberships()->create([
            'user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true,
            'pay_type' => 'profit_share',
        ]);
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

        // net_profit = 1250 gross - 750 boosting = 500 (the ticked 750 is exactly
        // within the cap, no excess). 80% share -> accrued = 400 before any payroll.
        $before = $this->getJson("/api/businesses/{$business->id}/team/{$membership->id}/salary")->assertOk()->json('summary');
        $this->assertEquals(400.0, $before['accrued']);
        $this->assertEquals(400.0, $before['pending']);

        // Pay exactly the accrued 400.
        $this->postJson("/api/businesses/{$business->id}/team/{$membership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 400, 'method' => 'bank_transfer',
        ])->assertCreated();

        // This must land at exactly 0 pending, never a negative "overpaid" figure —
        // paying someone their own profit share must not shrink the very number
        // their share was measured against.
        $after = $this->getJson("/api/businesses/{$business->id}/team/{$membership->id}/salary")->assertOk()->json('summary');
        $this->assertEquals(400.0, $after['accrued']);
        $this->assertEquals(0.0, $after['pending']);

        // The dashboard's true bottom-line profit still correctly reflects the real
        // payroll cost that just happened: 500 - 400 payroll = 100; 80% = 80.
        $range = 'start='.today()->startOfMonth()->toDateString().'&end='.today()->toDateString();
        $dashboard = $this->getJson("/api/businesses/{$business->id}/dashboard?{$range}")->assertOk()->json();
        $this->assertEquals(80.0, $dashboard['summary']['attributable_profit']);
    }
}
