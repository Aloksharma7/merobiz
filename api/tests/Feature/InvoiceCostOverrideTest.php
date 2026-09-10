<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCostOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Cost Override Co', 'slug' => 'cost-override-co-'.uniqid(), 'code' => 'CO'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'CO', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_an_admin_can_override_a_catalogue_products_cost_at_billing(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-ico1@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $product = $business->products()->create([
            'name' => 'Consulting hour', 'type' => 'service', 'unit' => 'hour', 'sale_price' => 2000, 'cost_price' => 500, 'tax_rate' => 0, 'active' => true,
        ]);

        // The product's own cost is 500, but this particular sale's real cost was
        // 800 (e.g. a subcontractor charged more this time) — billing overrides it.
        $invoice = app(InvoiceService::class)->create($business, $owner, [
            'invoice_date' => today()->toDateString(), 'status' => InvoiceStatus::Issued->value, 'discount_amount' => 0,
            'items' => [[
                'product_id' => $product->id, 'description' => $product->name, 'quantity' => 1,
                'unit_price' => 2000, 'unit_cost' => 800, 'discount_amount' => 0, 'tax_rate' => 0,
            ]],
        ]);

        $this->assertEquals(800.0, (float) $invoice->cost_amount);
        $this->assertEquals(1200.0, (float) $invoice->total_amount - (float) $invoice->cost_amount);
        // The product's own catalogue cost is untouched by a per-sale override.
        $this->assertEquals(500.0, (float) $product->fresh()->cost_price);
    }

    public function test_no_override_falls_back_to_the_catalogue_cost_as_before(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-ico2@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $product = $business->products()->create([
            'name' => 'Consulting hour', 'type' => 'service', 'unit' => 'hour', 'sale_price' => 2000, 'cost_price' => 500, 'tax_rate' => 0, 'active' => true,
        ]);

        // No unit_cost sent at all (the normal case for every existing sale flow) —
        // must keep resolving to the product's own cost, unchanged from before this fix.
        $invoice = app(InvoiceService::class)->create($business, $owner, [
            'invoice_date' => today()->toDateString(), 'status' => InvoiceStatus::Issued->value, 'discount_amount' => 0,
            'items' => [[
                'product_id' => $product->id, 'description' => $product->name, 'quantity' => 1,
                'unit_price' => 2000, 'discount_amount' => 0, 'tax_rate' => 0,
            ]],
        ]);

        $this->assertEquals(500.0, (float) $invoice->cost_amount);
    }
}
