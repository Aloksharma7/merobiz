<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\User;
use App\Services\DashboardService;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_dashboard_stays_within_a_reasonable_query_budget(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Query Budget Co', 'slug' => 'query-budget-'.uniqid(), 'code' => 'QBC'.rand(1000, 9999),
            'business_type' => 'product', 'category' => 'standard', 'currency' => 'NPR', 'invoice_prefix' => 'QBC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $membership = $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $business->ownerships()->create([
            'user_id' => $owner->id, 'ownership_percent' => 100, 'profit_share_percent' => 100,
            'effective_from' => today()->subYear()->toDateString(),
        ]);

        $product = $business->products()->create([
            'name' => 'Widget', 'type' => 'product', 'unit' => 'pcs',
            'sale_price' => 1000, 'cost_price' => 600, 'tax_rate' => 0, 'active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            app(\App\Services\InvoiceService::class)->create($business, $owner, [
                'customer_name' => 'Client '.$i,
                'invoice_date' => today()->toDateString(),
                'status' => InvoiceStatus::Issued->value,
                'discount_amount' => 0,
                'items' => [['product_id' => $product->id, 'description' => 'Widget', 'quantity' => 1, 'unit_price' => 1000, 'discount_amount' => 0, 'tax_rate' => 0]],
            ]);
        }

        $range = DateRange::fromRequest(request());
        $dashboard = app(DashboardService::class);

        DB::enableQueryLog();
        $dashboard->business($business, $owner, $membership, $range);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Before consolidating the per-metric SUM queries into one aggregate query,
        // a single dashboard load ran 112 queries (each metrics() call alone issued
        // 8 separate SUM/COUNT round trips, called ~10 times per load for the
        // current period, prior period, month-to-date, and the 6-point trend).
        // This guards against that regressing back in.
        $this->assertLessThan(60, $queryCount, "Dashboard load issued {$queryCount} queries — investigate before allowing this to grow further.");
    }
}
