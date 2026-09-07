<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_sale_counts_immediately_without_admin_approval(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);
        $employee = User::query()->create([
            'name' => 'Employee',
            'email' => 'employee@example.test',
            'password' => 'password',
        ]);

        $business = Business::query()->create([
            'owner_id' => $admin->id,
            'name' => 'Tech Champ Software',
            'slug' => 'tech-champ-software',
            'code' => 'TCS',
            'business_type' => 'service',
            'currency' => 'NPR',
            'invoice_prefix' => 'TCS',
            'default_tax_rate' => 0,
            'status' => 'active',
        ]);

        $business->memberships()->createMany([
            ['user_id' => $admin->id, 'role' => BusinessRole::Admin, 'active' => true],
            ['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'commission_rate' => 5, 'active' => true],
        ]);

        $product = $business->products()->create([
            'name' => 'Software subscription',
            'type' => 'service',
            'unit' => 'license',
            'sale_price' => 1000,
            'cost_price' => 500,
            'tax_rate' => 13,
            'active' => true,
        ]);

        $invoice = app(InvoiceService::class)->create($business, $employee, [
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [[
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => 1,
                'unit_price' => 1000,
                // A catalogue line's cost always comes from the product regardless of
                // role — only a custom line with no product can set an explicit cost.
                // Tax, however, is trusted from the request once the actor can manage
                // the catalogue (employees now can, same as admins).
                'unit_cost' => 1,
                'discount_amount' => 0,
                'tax_rate' => 0,
            ]],
        ]);

        // Every new sale is accepted automatically and requires no admin action.
        $this->assertSame('500.00', (string) $invoice->cost_amount);
        $this->assertSame('0.00', (string) $invoice->tax_amount);

        $dashboard = app(DashboardService::class);
        $start = CarbonImmutable::today()->startOfDay();
        $end = CarbonImmutable::today()->endOfDay();
        $financialMetrics = $dashboard->metrics($business, $start, $end);

        // The sale is part of business totals immediately.
        $this->assertSame(1000.0, $financialMetrics['net_sales']);
        $this->assertSame(500.0, $financialMetrics['cost_of_sales']);
        // Commission is earned on gross profit (1000 - 500 cost = 500), not on the full sale.
        $this->assertSame(25.0, $financialMetrics['commissions']);

        Sanctum::actingAs($employee);
        $this->getJson("/api/businesses/{$business->id}/products")
            ->assertOk()
            ->assertJsonPath('data.0.cost_price', null);
        $this->getJson("/api/businesses/{$business->id}/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.cost_amount', null)
            ->assertJsonPath('data.commission_amount', null)
            ->assertJsonPath('data.items.0.cost_price', null);
        $this->getJson("/api/businesses/{$business->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('summary.net_sales', 1000)
            ->assertJsonPath('summary.cost_of_sales', 0)
            ->assertJsonPath('summary.gross_profit', 0)
            ->assertJsonPath('summary.net_profit', 0)
            ->assertJsonPath('business.ownership_percent', 0);

        // There are intentionally no approval endpoints anymore.
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice->id}/verify")
            ->assertNotFound();
    }
}
