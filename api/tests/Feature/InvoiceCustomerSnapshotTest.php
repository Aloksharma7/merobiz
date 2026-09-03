<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceCustomerSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_issue_invoice_with_only_a_typed_customer_name(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'direct-owner@example.test',
            'password' => 'password',
        ]);
        $employee = User::query()->create([
            'name' => 'Employee',
            'email' => 'direct-employee@example.test',
            'password' => 'password',
        ]);
        $business = Business::query()->create([
            'owner_id' => $owner->id,
            'name' => 'AimersZone',
            'slug' => 'aimerszone-direct',
            'code' => 'AIMD',
            'business_type' => 'service',
            'currency' => 'NPR',
            'invoice_prefix' => 'AIMD',
            'default_tax_rate' => 0,
            'status' => 'active',
        ]);
        $business->memberships()->create([
            'user_id' => $employee->id,
            'role' => BusinessRole::Employee,
            'active' => true,
        ]);
        $product = $business->products()->create([
            'name' => 'AI Subscription',
            'type' => 'digital_subscription',
            'unit' => 'license',
            'sale_price' => 1500,
            'cost_price' => 800,
            'tax_rate' => 0,
            'active' => true,
        ]);

        Sanctum::actingAs($employee);

        $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Ram Sharma',
            'customer_phone' => '9800000000',
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->toDateString(),
            'status' => 'issued',
            'items' => [[
                'product_id' => $product->id,
                'description' => 'AI Subscription',
                'quantity' => 1,
                'unit_price' => 1500,
                'discount_amount' => 0,
                'tax_rate' => 0,
            ]],
        ])->assertCreated()
            ->assertJsonPath('invoice.customer_name', 'Ram Sharma')
            ->assertJsonPath('invoice.customer_phone', '9800000000')
            ->assertJsonPath('invoice.customer', null);

        $this->assertDatabaseHas('invoices', [
            'business_id' => $business->id,
            'customer_id' => null,
            'customer_name' => 'Ram Sharma',
            'customer_phone' => '9800000000',
        ]);
    }

    public function test_saved_customer_details_are_snapshotted_on_invoice(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'snapshot-owner@example.test',
            'password' => 'password',
        ]);
        $business = Business::query()->create([
            'owner_id' => $owner->id,
            'name' => 'TechChamp Software',
            'slug' => 'techchamp-snapshot',
            'code' => 'TCSN',
            'business_type' => 'service',
            'currency' => 'NPR',
            'invoice_prefix' => 'TCSN',
            'default_tax_rate' => 0,
            'status' => 'active',
        ]);
        $business->memberships()->create([
            'user_id' => $owner->id,
            'role' => BusinessRole::Owner,
            'active' => true,
        ]);
        $customer = $business->customers()->create([
            'name' => 'ABC Traders',
            'phone' => '9811111111',
            'address' => 'Kathmandu',
            'active' => true,
        ]);
        $product = $business->products()->create([
            'name' => 'Software Service',
            'type' => 'service',
            'unit' => 'service',
            'sale_price' => 2000,
            'cost_price' => 1000,
            'tax_rate' => 0,
            'active' => true,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_id' => $customer->id,
            'invoice_date' => today()->toDateString(),
            'status' => 'issued',
            'items' => [[
                'product_id' => $product->id,
                'description' => 'Software Service',
                'quantity' => 1,
                'unit_price' => 2000,
                'unit_cost' => 1000,
                'discount_amount' => 0,
                'tax_rate' => 0,
            ]],
        ])->assertCreated();

        $invoiceId = $response->json('invoice.id');
        $customer->update(['name' => 'ABC Traders Renamed', 'phone' => '9822222222']);

        $this->getJson("/api/businesses/{$business->id}/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.customer_name', 'ABC Traders')
            ->assertJsonPath('data.customer_phone', '9811111111');
    }
}
