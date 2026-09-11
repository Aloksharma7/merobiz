<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThesisBusinessSalesManagementTest extends TestCase
{
    use RefreshDatabase;

    private function business(User $owner, string $category, string $prefix): Business
    {
        return Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Sales Co '.$prefix, 'slug' => 'sales-co-'.strtolower($prefix).'-'.uniqid(), 'code' => $prefix.rand(1000, 9999),
            'business_type' => 'service', 'category' => $category, 'currency' => 'NPR', 'invoice_prefix' => $prefix,
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
    }

    public function test_an_employee_can_delete_a_coworkers_sale_in_an_installment_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-tbsm1@example.test', 'password' => 'password']);
        $employeeOne = User::query()->create(['name' => 'Employee One', 'email' => 'employee-tbsm1a@example.test', 'password' => 'password']);
        $employeeTwo = User::query()->create(['name' => 'Employee Two', 'email' => 'employee-tbsm1b@example.test', 'password' => 'password']);
        $business = $this->business($owner, 'installment', 'TS1');
        $business->memberships()->createMany([
            ['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true],
            ['user_id' => $employeeOne->id, 'role' => BusinessRole::Employee, 'active' => true],
            ['user_id' => $employeeTwo->id, 'role' => BusinessRole::Employee, 'active' => true],
        ]);

        Sanctum::actingAs($employeeOne);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Topic', 'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 5000,
        ])->assertCreated()->json('project');
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $project['id'], 'invoice_date' => today()->toDateString(), 'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [['description' => 'Consultation', 'quantity' => 1, 'unit_price' => 5000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');

        // Employee Two never created this sale, but a thesis business trusts the
        // whole small team to manage each other's sales, same as an admin.
        Sanctum::actingAs($employeeTwo);
        $this->deleteJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}")
            ->assertOk()
            ->assertJsonPath('message', 'Sale deleted.');
    }

    public function test_an_employee_cannot_delete_a_coworkers_sale_in_a_standard_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-tbsm2@example.test', 'password' => 'password']);
        $employeeOne = User::query()->create(['name' => 'Employee One', 'email' => 'employee-tbsm2a@example.test', 'password' => 'password']);
        $employeeTwo = User::query()->create(['name' => 'Employee Two', 'email' => 'employee-tbsm2b@example.test', 'password' => 'password']);
        $business = $this->business($owner, 'standard', 'TS2');
        $business->memberships()->createMany([
            ['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true],
            ['user_id' => $employeeOne->id, 'role' => BusinessRole::Employee, 'active' => true],
            ['user_id' => $employeeTwo->id, 'role' => BusinessRole::Employee, 'active' => true],
        ]);

        Sanctum::actingAs($employeeOne);
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Client A', 'invoice_date' => today()->toDateString(), 'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [['description' => 'Consultation', 'quantity' => 1, 'unit_price' => 5000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');

        Sanctum::actingAs($employeeTwo);
        $this->deleteJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}")->assertForbidden();
    }

    public function test_an_employee_can_undo_a_coworkers_payment_in_an_installment_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-tbsm3@example.test', 'password' => 'password']);
        $employeeOne = User::query()->create(['name' => 'Employee One', 'email' => 'employee-tbsm3a@example.test', 'password' => 'password']);
        $employeeTwo = User::query()->create(['name' => 'Employee Two', 'email' => 'employee-tbsm3b@example.test', 'password' => 'password']);
        $business = $this->business($owner, 'installment', 'TS3');
        $business->memberships()->createMany([
            ['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true],
            ['user_id' => $employeeOne->id, 'role' => BusinessRole::Employee, 'active' => true],
            ['user_id' => $employeeTwo->id, 'role' => BusinessRole::Employee, 'active' => true],
        ]);

        Sanctum::actingAs($employeeOne);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Topic', 'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 5000,
        ])->assertCreated()->json('project');
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $project['id'], 'invoice_date' => today()->toDateString(), 'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [['description' => 'Consultation', 'quantity' => 1, 'unit_price' => 5000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $payment = $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 2000, 'method' => 'cash',
        ])->assertCreated()->json('payment');

        Sanctum::actingAs($employeeTwo);
        $this->deleteJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments/{$payment['id']}")
            ->assertOk()
            ->assertJsonPath('message', 'Payment deleted.');
    }

    public function test_an_employee_cannot_undo_a_coworkers_payment_in_a_standard_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-tbsm4@example.test', 'password' => 'password']);
        $employeeOne = User::query()->create(['name' => 'Employee One', 'email' => 'employee-tbsm4a@example.test', 'password' => 'password']);
        $employeeTwo = User::query()->create(['name' => 'Employee Two', 'email' => 'employee-tbsm4b@example.test', 'password' => 'password']);
        $business = $this->business($owner, 'standard', 'TS4');
        $business->memberships()->createMany([
            ['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true],
            ['user_id' => $employeeOne->id, 'role' => BusinessRole::Employee, 'active' => true],
            ['user_id' => $employeeTwo->id, 'role' => BusinessRole::Employee, 'active' => true],
        ]);

        Sanctum::actingAs($employeeOne);
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Client A', 'invoice_date' => today()->toDateString(), 'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [['description' => 'Consultation', 'quantity' => 1, 'unit_price' => 5000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $payment = $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 2000, 'method' => 'cash',
        ])->assertCreated()->json('payment');

        Sanctum::actingAs($employeeTwo);
        $this->deleteJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments/{$payment['id']}")->assertForbidden();
    }
}
