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

class ExpenseExemptionCapTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Exemption Cap Co', 'slug' => 'exemption-cap-co-'.uniqid(), 'code' => 'EC'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'EC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        app(OwnershipService::class)->schedule($business, $owner->id, 80, 80, today()->toImmutable()->startOfDay(), null, $owner);

        return $business;
    }

    private function sell(Business $business, User $owner, float $cost): void
    {
        $product = $business->products()->create([
            'name' => 'AI check', 'type' => 'service', 'unit' => 'session', 'sale_price' => 2000, 'cost_price' => $cost, 'tax_rate' => 0, 'active' => true,
        ]);
        $invoice = app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Walk-in', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'AI check', 'quantity' => 1, 'unit_price' => 2000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);
        app(PaymentService::class)->record($business, $invoice, $owner, [
            'payment_date' => today()->toDateString(), 'amount' => 2000, 'method' => 'bank_transfer',
        ]);
    }

    private function expense(Business $business, float $amount, bool $affectsProfit): void
    {
        $this->postJson("/api/businesses/{$business->id}/expenses", [
            'category' => 'Cost', 'expense_date' => today()->toDateString(), 'amount' => $amount,
            'payment_method' => 'bank_transfer', 'affects_profit' => $affectsProfit,
        ])->assertCreated()->assertJsonPath('expense.affects_profit', $affectsProfit);
    }

    /** @return array{net_profit: float, attributable_profit: float, available_balance: float} */
    private function figures(Business $business): array
    {
        $range = 'start='.today()->startOfMonth()->toDateString().'&end='.today()->toDateString();
        $data = $this->getJson("/api/businesses/{$business->id}/dashboard?{$range}")->assertOk()->json();

        return [
            'net_profit' => $data['summary']['net_profit'],
            'attributable_profit' => $data['summary']['attributable_profit'],
            'available_balance' => $data['available_balance']['available_balance'],
        ];
    }

    public function test_scenario_one_boosting_then_two_ticked_expenses(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-eec1@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $this->sell($business, $owner, 750);
        Sanctum::actingAs($owner);

        $this->expense($business, 750, true);  // normal: boosting
        $this->expense($business, 750, false); // ticked: within the 750 cap
        $this->expense($business, 500, false); // ticked: 500 beyond the cap falls back to reducing profit

        $figures = $this->figures($business);
        $this->assertEquals(0.0, $figures['net_profit']);
        $this->assertEquals(0.0, $figures['attributable_profit']);
        $this->assertEquals(0.0, $figures['available_balance']);
    }

    public function test_scenario_two_two_ticked_expenses_exactly_matching_the_cap(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-eec2@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $this->sell($business, $owner, 750);
        Sanctum::actingAs($owner);

        $this->expense($business, 750, true);  // normal: boosting
        $this->expense($business, 300, false); // ticked
        $this->expense($business, 450, false); // ticked — 300+450 = 750, exactly the cap
        $this->expense($business, 500, true);  // normal

        $figures = $this->figures($business);
        $this->assertEquals(0.0, $figures['net_profit']);
        $this->assertEquals(0.0, $figures['attributable_profit']);
        $this->assertEquals(0.0, $figures['available_balance']);
    }

    public function test_scenario_three_three_sales_with_a_combined_cap(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-eec3@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $this->sell($business, $owner, 750);
        $this->sell($business, $owner, 750);
        $this->sell($business, $owner, 750);
        Sanctum::actingAs($owner);

        $this->expense($business, 2000, true);  // normal: boosting
        $this->expense($business, 4000, false); // ticked — capped at the combined 2250 cost, 1750 excess

        $figures = $this->figures($business);
        $this->assertEquals(0.0, $figures['net_profit']);
        $this->assertEquals(0.0, $figures['attributable_profit']);
        $this->assertEquals(0.0, $figures['available_balance']);
    }
}
