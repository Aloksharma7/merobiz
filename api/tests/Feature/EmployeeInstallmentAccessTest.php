<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeInstallmentAccessTest extends TestCase
{
    use RefreshDatabase;

    private function installmentBusinessWithEmployee(): array
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Enlighten Research', 'slug' => 'enlighten-'.uniqid(), 'code' => 'ER'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'installment', 'currency' => 'NPR', 'invoice_prefix' => 'ERC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee@example.test', 'password' => 'password']);
        $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'full_control' => false, 'active' => true]);

        return [$owner, $business, $employee];
    }

    public function test_employee_can_manage_projects_writers_and_customers_in_an_installment_business(): void
    {
        [, $business, $employee] = $this->installmentBusinessWithEmployee();

        Sanctum::actingAs($employee);

        $this->getJson("/api/businesses/{$business->id}/projects")->assertOk();

        $writer = $this->postJson("/api/businesses/{$business->id}/writers", ['name' => 'Rita Writer'])
            ->assertCreated()->json('writer');

        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Bishal Karki', 'topic' => 'Impact of microfinance', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 25000, 'writer_id' => $writer['id'],
        ])->assertCreated()->json('project');

        $this->assertSame($writer['id'], $project['current_writer']['id']);

        $this->getJson("/api/businesses/{$business->id}/writers/{$writer['id']}")->assertOk();
        $this->getJson("/api/businesses/{$business->id}/customers")->assertOk();
    }

    public function test_employee_never_sees_approved_profit_figures_on_a_project(): void
    {
        [$owner, $business, $employee] = $this->installmentBusinessWithEmployee();

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Anisha Rai', 'topic' => 'Rural tourism', 'course' => 'BBA', 'work' => 'Report',
            'deal_amount' => 18000, 'writer_payment_amount' => 6000,
        ])->assertCreated()->json('project');

        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/profit-approvals", [
            'approved_on' => today()->toDateString(), 'amount' => 8000,
        ])->assertCreated();

        $ownerView = $this->getJson("/api/businesses/{$business->id}/projects/{$project['id']}")->assertOk()->json('data');
        $this->assertEquals(8000.0, $ownerView['approved_profit_total']);
        $this->assertCount(1, $ownerView['profit_approvals']);

        Sanctum::actingAs($employee);
        $employeeView = $this->getJson("/api/businesses/{$business->id}/projects/{$project['id']}")->assertOk()->json('data');
        $this->assertNull($employeeView['approved_profit_total']);
        $this->assertNull($employeeView['profit_approvals']);
        // Deal and writer-payment figures remain visible — only the profit take is hidden.
        $this->assertEquals(18000.0, $employeeView['deal_amount']);
        $this->assertEquals(6000.0, $employeeView['writer_payment_amount']);
    }

    public function test_employee_cannot_approve_profit_or_reach_reports_and_distributions(): void
    {
        [$owner, $business, $employee] = $this->installmentBusinessWithEmployee();

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Dipesh Shrestha', 'topic' => 'E-commerce adoption', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 20000,
        ])->assertCreated()->json('project');

        Sanctum::actingAs($employee);
        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/profit-approvals", [
            'approved_on' => today()->toDateString(), 'amount' => 5000,
        ])->assertForbidden();

        $this->getJson("/api/businesses/{$business->id}/reports/profit-loss")->assertForbidden();
        $this->getJson("/api/businesses/{$business->id}/profit-distributions")->assertForbidden();
        $this->postJson("/api/businesses/{$business->id}/profit-distributions", [
            'profit_allocation_id' => 1, 'distribution_date' => today()->toDateString(), 'amount' => 1000, 'method' => 'bank_transfer',
        ])->assertForbidden();
        $this->postJson("/api/businesses/{$business->id}/profit-withdrawals", [
            'withdrawn_on' => today()->toDateString(), 'amount' => 1000,
        ])->assertForbidden();
    }

    public function test_employee_can_record_sales_against_a_project_without_picking_a_catalogue_product(): void
    {
        [, $business, $employee] = $this->installmentBusinessWithEmployee();

        Sanctum::actingAs($employee);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Raju Thapa', 'topic' => 'AI adoption in retail', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 10000,
        ])->assertCreated()->json('project');

        $due = fn () => $this->getJson("/api/businesses/{$business->id}/projects/{$project['id']}")->json('data.due_amount');

        // First partial sale — same free-text line item shape the "record a sale" form sends, no product_id.
        $firstInvoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $project['id'], 'invoice_date' => today()->toDateString(), 'status' => 'issued',
            'discount_amount' => 0,
            'items' => [['description' => 'Thesis — AI adoption in retail', 'quantity' => 1, 'unit_price' => 5000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $this->postJson("/api/businesses/{$business->id}/invoices/{$firstInvoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 5000, 'method' => 'cash',
        ])->assertCreated();
        $this->assertEquals(5000.0, $due());

        // Final payment closing out the remaining due — this is the exact scenario that used to
        // fail with "Employees must select a product or service from the business catalogue."
        $secondInvoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $project['id'], 'invoice_date' => today()->toDateString(), 'status' => 'issued',
            'discount_amount' => 0,
            'items' => [['description' => 'Thesis — AI adoption in retail', 'quantity' => 1, 'unit_price' => 5000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $this->postJson("/api/businesses/{$business->id}/invoices/{$secondInvoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 5000, 'method' => 'cash',
        ])->assertCreated();
        $this->assertEquals(0.0, $due());
    }
}
