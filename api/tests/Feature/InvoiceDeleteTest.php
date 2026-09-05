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

class InvoiceDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_admin_can_delete_a_sale_and_it_restores_stock_and_cash_collected(): void
    {
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin-del@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-del@example.test', 'password' => 'password']);

        $business = Business::query()->create([
            'owner_id' => $admin->id,
            'name' => 'Gadget Shop',
            'slug' => 'gadget-shop-'.uniqid(),
            'code' => 'GS',
            'business_type' => 'physical_retail',
            'currency' => 'NPR',
            'invoice_prefix' => 'GS',
            'default_tax_rate' => 0,
            'status' => 'active',
        ]);

        $business->memberships()->createMany([
            ['user_id' => $admin->id, 'role' => BusinessRole::Admin, 'active' => true],
            ['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true],
        ]);

        $product = $business->products()->create([
            'name' => 'Wireless Mouse',
            'type' => 'product',
            'unit' => 'unit',
            'sale_price' => 1000,
            'cost_price' => 400,
            'tax_rate' => 0,
            'track_inventory' => true,
            'stock_quantity' => 10,
            'reorder_level' => 1,
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
                'quantity' => 2,
                'unit_price' => 1000,
                'discount_amount' => 0,
                'tax_rate' => 0,
            ]],
        ]);

        $this->assertSame(8.0, (float) $product->fresh()->stock_quantity);

        Sanctum::actingAs($admin);
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice->id}/payments", [
            'payment_date' => today()->toDateString(),
            'amount' => 2000,
            'method' => 'cash',
        ])->assertCreated();

        $dashboard = app(DashboardService::class);
        $start = CarbonImmutable::today()->startOfDay();
        $end = CarbonImmutable::today()->endOfDay();
        $this->assertSame(2000.0, $dashboard->metrics($business, $start, $end)['cash_collected']);

        // An employee cannot delete the sale, even though they created it and can
        // cancel/edit their own invoices elsewhere.
        Sanctum::actingAs($employee);
        $this->deleteJson("/api/businesses/{$business->id}/invoices/{$invoice->id}")->assertForbidden();

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/businesses/{$business->id}/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Sale deleted.');

        $this->assertNull($invoice->fresh());
        $this->assertSame(0, $business->payments()->count());
        $this->assertSame(10.0, (float) $product->fresh()->stock_quantity);
        $this->assertSame(0.0, $dashboard->metrics($business, $start, $end)['cash_collected']);
    }
}
