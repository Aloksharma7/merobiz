<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\User;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectRefundTest extends TestCase
{
    use RefreshDatabase;

    private function installmentBusiness(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Enlighten Research', 'slug' => 'enlighten-'.uniqid(), 'code' => 'ER'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'installment', 'currency' => 'NPR', 'invoice_prefix' => 'ERC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_aborting_a_project_and_recording_a_refund_clears_the_due_amount(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Impact of AI', 'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 20000,
        ])->assertCreated()->json('project');

        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $project['id'], 'invoice_date' => today()->toDateString(), 'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [['description' => 'Partial payment', 'quantity' => 1, 'unit_price' => 8000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 8000, 'method' => 'bank_transfer',
        ])->assertCreated();

        // Before aborting: 8000 collected out of a 20000 deal, 12000 still due.
        $before = $this->getJson("/api/businesses/{$business->id}/projects/{$project['id']}")->json('data');
        $this->assertEquals(8000.0, $before['collected_amount']);
        $this->assertEquals(12000.0, $before['due_amount']);

        // Client decides not to continue — mark the file cancelled and refund what was collected.
        $this->putJson("/api/businesses/{$business->id}/projects/{$project['id']}", ['work_status' => 'cancelled'])->assertOk();
        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/refunds", [
            'refunded_on' => today()->toDateString(), 'amount' => 8000, 'notes' => 'Client cancelled mid-way',
        ])->assertCreated();

        $after = $this->getJson("/api/businesses/{$business->id}/projects/{$project['id']}")->json('data');
        $this->assertSame('cancelled', $after['work_status']);
        $this->assertEquals(8000.0, $after['collected_amount']);
        $this->assertEquals(8000.0, $after['refunded_amount']);
        $this->assertEquals(0.0, $after['net_collected_amount']);
        // Cancelled means nothing further is owed — not the remaining 12000 of the original deal.
        $this->assertEquals(0.0, $after['due_amount']);
        $this->assertCount(1, $after['refunds']);
    }

    public function test_refunding_more_than_was_collected_is_rejected(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client B', 'topic' => 'Topic', 'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 10000,
        ])->assertCreated()->json('project');

        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $project['id'], 'invoice_date' => today()->toDateString(), 'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [['description' => 'Payment', 'quantity' => 1, 'unit_price' => 3000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 3000, 'method' => 'cash',
        ])->assertCreated();

        $response = $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/refunds", [
            'refunded_on' => today()->toDateString(), 'amount' => 5000,
        ])->assertUnprocessable();

        $message = $response->json('errors.amount.0');
        $this->assertStringContainsString('5,000.00', $message);
        $this->assertStringContainsString('3,000.00', $message);
    }

    public function test_a_second_refund_cannot_exceed_what_remains_after_the_first(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner3@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client C', 'topic' => 'Topic', 'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 10000,
        ])->assertCreated()->json('project');

        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $project['id'], 'invoice_date' => today()->toDateString(), 'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [['description' => 'Payment', 'quantity' => 1, 'unit_price' => 10000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 10000, 'method' => 'cash',
        ])->assertCreated();

        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/refunds", [
            'refunded_on' => today()->toDateString(), 'amount' => 6000,
        ])->assertCreated();

        // Only 4000 remains refundable (10000 collected - 6000 already refunded).
        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/refunds", [
            'refunded_on' => today()->toDateString(), 'amount' => 4001,
        ])->assertUnprocessable();

        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/refunds", [
            'refunded_on' => today()->toDateString(), 'amount' => 4000,
        ])->assertCreated();
    }

    public function test_a_refund_reduces_business_cash_collected_and_net_profit(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner4@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client D', 'topic' => 'Topic', 'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 10000,
        ])->assertCreated()->json('project');

        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $project['id'], 'invoice_date' => today()->toDateString(), 'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [['description' => 'Payment', 'quantity' => 1, 'unit_price' => 10000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 10000, 'method' => 'cash',
        ])->assertCreated();

        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/refunds", [
            'refunded_on' => today()->toDateString(), 'amount' => 4000,
        ])->assertCreated();

        $dashboard = app(DashboardService::class);
        $start = CarbonImmutable::today()->startOfDay();
        $end = CarbonImmutable::today()->endOfDay();
        $metrics = $dashboard->metrics($business->fresh(), $start, $end);

        $this->assertSame(4000.0, $metrics['refunds']);
        $this->assertSame(6000.0, $metrics['cash_collected']);
    }
}
