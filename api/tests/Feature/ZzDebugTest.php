<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZzDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-sales@example.test', 'password' => 'password', 'preferred_currency' => 'NPR']);
        $employee = User::query()->create(['name' => 'Employee One', 'email' => 'employee-one@example.test', 'password' => 'password', 'preferred_currency' => 'NPR']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'AimersZone', 'slug' => 'aim2', 'code' => 'AIM2',
            'business_type' => 'digital_subscription', 'currency' => 'NPR', 'invoice_prefix' => 'AIM2',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->createMany([
            ['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true],
            ['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'commission_rate' => 5, 'active' => true],
        ]);
        $customer = $business->customers()->create(['name' => 'Shared Customer', 'active' => true]);
        $product = $business->products()->create([
            'name' => 'AI Tool', 'type' => 'digital_subscription', 'unit' => 'license',
            'sale_price' => 1000, 'cost_price' => 500, 'tax_rate' => 0, 'active' => true,
        ]);

        $invoice = app(InvoiceService::class)->create($business, $employee, [
            'customer_id' => $customer->id,
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [[
                'product_id' => $product->id, 'description' => 'AI Tool', 'quantity' => 1,
                'unit_price' => 1000, 'discount_amount' => 0, 'tax_rate' => 0,
            ]],
        ]);

        fwrite(STDERR, 'invoice created_by: '.$invoice->created_by.' employee id: '.$employee->id.PHP_EOL);
        fwrite(STDERR, 'invoice date: '.$invoice->invoice_date.' status: '.$invoice->status->value.' business_id: '.$invoice->business_id.PHP_EOL);

        $dashboard = app(DashboardService::class);
        $start = CarbonImmutable::now()->startOfMonth();
        $end = CarbonImmutable::now()->endOfDay();
        $metrics = $dashboard->metrics($business, $start, $end, $employee);
        fwrite(STDERR, print_r($metrics, true));

        fwrite(STDERR, 'raw invoice count for business: '.Invoice::query()->where('business_id', $business->id)->count().PHP_EOL);
        fwrite(STDERR, 'raw invoice count for creator: '.Invoice::query()->where('business_id', $business->id)->where('created_by', $employee->id)->count().PHP_EOL);
        fwrite(STDERR, 'raw invoice_date column: '.\Illuminate\Support\Facades\DB::table('invoices')->value('invoice_date').PHP_EOL);
        fwrite(STDERR, 'between filter start: '.$start->toDateString().' end: '.$end->toDateString().PHP_EOL);
        fwrite(STDERR, 'between count: '.Invoice::query()->whereBetween('invoice_date', [$start->toDateString(), $end->toDateString()])->count().PHP_EOL);
        fwrite(STDERR, 'status in count: '.Invoice::query()->whereIn('status', \App\Services\DashboardService::LIVE_INVOICE_STATUSES)->count().PHP_EOL);

        $this->assertTrue(true);
    }
}
