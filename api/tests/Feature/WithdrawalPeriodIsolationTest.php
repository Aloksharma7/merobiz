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

class WithdrawalPeriodIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_withdrawal_from_a_past_period_does_not_reduce_a_different_periods_expected_profit(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-wpi@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Withdrawal Isolation Co', 'slug' => 'withdrawal-isolation-co-'.uniqid(), 'code' => 'WI'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'WI', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        app(OwnershipService::class)->schedule($business, $owner->id, 100, 100, today()->subYear()->toImmutable()->startOfDay(), null, $owner);
        // lifetime_profit_earned spans from the business's own creation date — back
        // it far enough that this test's backdated "last month" invoice is included,
        // matching a real business (which always predates any invoice on it).
        $business->forceFill(['created_at' => today()->subYear()])->save();

        $product = $business->products()->create([
            'name' => 'Widget', 'type' => 'product', 'unit' => 'pcs', 'sale_price' => 1000, 'cost_price' => 0, 'tax_rate' => 0, 'active' => true,
        ]);

        // A sale + full withdrawal last month.
        $lastMonthDate = today()->subMonthNoOverflow();
        $oldInvoice = app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Walk-in', 'invoice_date' => $lastMonthDate->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'Widget', 'quantity' => 1, 'unit_price' => 1000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);
        app(PaymentService::class)->record($business, $oldInvoice, $owner, [
            'payment_date' => $lastMonthDate->toDateString(), 'amount' => 1000, 'method' => 'bank_transfer',
        ]);
        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/profit-withdrawals", [
            'withdrawn_on' => $lastMonthDate->toDateString(), 'amount' => 1000,
        ])->assertCreated();

        // A brand new, unrelated sale this month — nothing withdrawn against it.
        $newInvoice = app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Walk-in', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'Widget', 'quantity' => 1, 'unit_price' => 500, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);
        app(PaymentService::class)->record($business, $newInvoice, $owner, [
            'payment_date' => today()->toDateString(), 'amount' => 500, 'method' => 'bank_transfer',
        ]);

        $range = 'start='.today()->startOfMonth()->toDateString().'&end='.today()->toDateString();
        $data = $this->getJson("/api/businesses/{$business->id}/dashboard?{$range}")->assertOk()->json();

        // This month's expected profit must be this month's own 500 sale, completely
        // untouched by last month's already-settled withdrawal.
        $this->assertEquals(500.0, $data['summary']['attributable_profit']);
        // But lifetime figures correctly reflect the full history.
        $this->assertEquals(1500.0, $data['summary']['lifetime_profit_earned']);
        $this->assertEquals(1000.0, $data['summary']['lifetime_profit_withdrawn']);
        $this->assertEquals(500.0, $data['summary']['profit_available_to_withdraw']);
    }
}
