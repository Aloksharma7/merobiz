<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseAffectsProfitTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expense_marked_already_priced_into_a_sale_reduces_balance_but_not_profit(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-aep@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Affects Profit Co', 'slug' => 'affects-profit-co-'.uniqid(), 'code' => 'AP'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'AP', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true, 'commission_rate' => 0]);
        $product = $business->products()->create([
            'name' => 'AI check', 'type' => 'service', 'unit' => 'session', 'sale_price' => 1000, 'cost_price' => 400, 'tax_rate' => 0, 'active' => true,
        ]);

        $invoice = app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Walk-in', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'AI check', 'quantity' => 1, 'unit_price' => 1000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice->id}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 1000, 'method' => 'bank_transfer',
        ])->assertCreated();

        // A normal expense: reduces both profit and balance, exactly as before this feature.
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Office supplies', 'expense_date' => today()->toDateString(), 'amount' => 100,
            'payment_method' => 'cash',
        ])->assertCreated()->assertJsonPath('expense.affects_profit', true);

        // The AI subscription cost was already baked into the item's cost_price above —
        // this expense is just the cash catching up to it, so it must not also cut profit.
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'AI subscription', 'expense_date' => today()->toDateString(), 'amount' => 150,
            'payment_method' => 'cash', 'affects_profit' => false,
        ])->assertCreated()->assertJsonPath('expense.affects_profit', false);

        $dashboard = $this->getJson("/api/businesses/{$business->id}/dashboard?start=".today()->startOfMonth()->toDateString().'&end='.today()->toDateString())
            ->assertOk()
            ->json();

        // gross_profit = 1000 - 400 = 600; only the 100 profit-affecting expense applies.
        $this->assertEquals(600.0, $dashboard['summary']['gross_profit']);
        $this->assertEquals(100.0, $dashboard['summary']['expenses']);
        $this->assertEquals(500.0, $dashboard['summary']['net_profit']);

        // Both expenses (100 + 150 = 250) still reduce real cash: 1000 collected - 250 = 750.
        $this->assertEquals(750.0, $dashboard['available_balance']['available_balance']);
    }
}
