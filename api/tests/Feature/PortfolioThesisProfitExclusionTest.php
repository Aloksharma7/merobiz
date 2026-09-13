<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortfolioThesisProfitExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_thesis_businesss_approved_project_profit_never_counts_toward_the_combined_portfolio_profit(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-ptp1@example.test', 'password' => 'password']);

        $standard = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Standard Retail', 'slug' => 'standard-retail-'.uniqid(), 'code' => 'SR'.rand(1000, 9999),
            'business_type' => 'physical_retail', 'category' => 'standard', 'currency' => 'NPR', 'invoice_prefix' => 'SR',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $standard->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $standard->ownerships()->create(['user_id' => $owner->id, 'ownership_percent' => 100, 'profit_share_percent' => 100, 'effective_from' => today()->subYear()->toDateString()]);
        $product = $standard->products()->create([
            'name' => 'Widget', 'type' => 'product', 'unit' => 'unit', 'sale_price' => 1000, 'cost_price' => 200, 'tax_rate' => 0,
            'track_inventory' => false, 'active' => true,
        ]);

        $thesis = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Thesis Writers', 'slug' => 'thesis-writers-'.uniqid(), 'code' => 'TW'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'installment', 'currency' => 'NPR', 'invoice_prefix' => 'TW',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $thesis->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $thesis->ownerships()->create(['user_id' => $owner->id, 'ownership_percent' => 100, 'profit_share_percent' => 100, 'effective_from' => today()->subYear()->toDateString()]);

        Sanctum::actingAs($owner);
        // Standard business: a single sale worth 800 in real net profit (1000 - 200 cost).
        $this->postJson("/api/businesses/{$standard->id}/invoices", [
            'customer_name' => 'Walk-in', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => 'Widget', 'quantity' => 1, 'unit_price' => 1000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated();

        // Thesis business: a large manually-approved project profit that must never
        // be blended into the portfolio's combined "expected profit" figures.
        $project = $this->postJson("/api/businesses/{$thesis->id}/projects", [
            'client_name' => 'Big Client', 'topic' => 'Topic', 'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 50000,
        ])->assertCreated()->json('project');
        $this->postJson("/api/businesses/{$thesis->id}/projects/{$project['id']}/profit-approvals", [
            'approved_on' => today()->toDateString(), 'amount' => 30000,
        ])->assertCreated();

        $response = $this->getJson('/api/portfolio/dashboard')->assertOk()->json();

        $this->assertEquals(800.0, $response['summary']['net_profit']);
        $this->assertEquals(800.0, $response['summary']['attributable_profit']);
        $this->assertEquals(800.0, $response['month_to_date']['profit']);
    }
}
