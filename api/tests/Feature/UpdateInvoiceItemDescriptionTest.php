<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateInvoiceItemDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Bill Wording Co', 'slug' => 'bill-wording-co-'.uniqid(), 'code' => 'BW'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'standard', 'currency' => 'NPR', 'invoice_prefix' => 'BWC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_an_admin_can_rename_a_line_item_on_an_already_issued_invoice_without_changing_totals(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-uiid1@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $product = $business->products()->create([
            'name' => 'AI Detection Check', 'type' => 'service', 'unit' => 'session', 'sale_price' => 2000, 'cost_price' => 500, 'tax_rate' => 0, 'active' => true,
        ]);

        Sanctum::actingAs($owner);
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Client A', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => 2000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $itemId = $invoice['items'][0]['id'];

        // The product's own catalogue name should never appear verbatim on a bill
        // that the client can see it was for "consulting services" instead.
        $this->patchJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/items/{$itemId}", [
            'description' => 'Consulting services',
        ])->assertOk()->assertJsonPath('item.description', 'Consulting services');

        $fresh = $this->getJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}")->assertOk()->json('data');
        $this->assertSame('Consulting services', $fresh['items'][0]['description']);
        // Nothing financial moved.
        $this->assertEquals(2000.0, $fresh['total_amount']);
        $this->assertEquals(2000.0, $fresh['balance_amount']);
        // The catalogue product itself is untouched.
        $this->assertSame('AI Detection Check', $product->fresh()->name);
    }

    public function test_an_employee_without_sales_manage_cannot_rename_someone_elses_line_item(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-uiid2@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-uiid2@example.test', 'password' => 'password']);
        $business = $this->business($owner);
        $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true]);

        Sanctum::actingAs($owner);
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Client A', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['description' => 'Custom line', 'quantity' => 1, 'unit_price' => 2000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $itemId = $invoice['items'][0]['id'];

        Sanctum::actingAs($employee);
        $this->patchJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/items/{$itemId}", [
            'description' => 'Renamed by an outsider',
        ])->assertForbidden();
    }
}
