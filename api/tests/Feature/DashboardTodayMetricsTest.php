<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTodayMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_todays_metrics_only_count_sales_dated_today(): void
    {
        // Pinned mid-month so "yesterday" can never cross into a different month
        // and fall outside the month_to_date window being asserted below.
        Carbon::setTestNow('2025-06-15');

        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dtm1@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Gadget Shop', 'slug' => 'gadget-shop-'.uniqid(), 'code' => 'GS'.rand(1000, 9999),
            'business_type' => 'physical_retail', 'category' => 'standard', 'currency' => 'NPR', 'invoice_prefix' => 'GS',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        app(InvoiceService::class)->create($business, $owner, [
            'invoice_date' => today()->subDay()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'customer_name' => 'Yesterday Client',
            'discount_amount' => 0,
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);
        app(InvoiceService::class)->create($business, $owner, [
            'invoice_date' => today()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'customer_name' => 'Today Client',
            'discount_amount' => 0,
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 3000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);

        Sanctum::actingAs($owner);
        $response = $this->getJson("/api/businesses/{$business->id}/dashboard")->assertOk()->json();

        $this->assertEquals(3000.0, $response['today']['sales']);
        $this->assertEquals(4000.0, $response['month_to_date']['sales']);
    }
}
