<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\ProjectProfitApproval;
use App\Models\ProjectRefund;
use App\Models\ProjectWriterAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermanentDeleteProjectTest extends TestCase
{
    use RefreshDatabase;

    private function installmentBusiness(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Permanent Delete Co', 'slug' => 'permanent-delete-co-'.uniqid(), 'code' => 'PD'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'installment', 'currency' => 'NPR', 'invoice_prefix' => 'PDC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_deleting_a_project_permanently_removes_it_and_its_own_records_but_keeps_invoices_and_payments(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-pdp1@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);
        $writer = $business->writers()->create(['name' => 'Priya Adhikari', 'active' => true]);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Impact of AI', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 20000, 'writer_id' => $writer->id,
        ])->assertCreated()->json('project');

        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $project['id'], 'invoice_date' => today()->toDateString(), 'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [['description' => 'Down payment', 'quantity' => 1, 'unit_price' => 8000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $paymentId = $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 8000, 'method' => 'bank_transfer',
        ])->assertCreated()->json('payment.id');

        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/profit-approvals", [
            'approved_on' => today()->toDateString(), 'amount' => 3000,
        ])->assertCreated();
        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/refunds", [
            'refunded_on' => today()->toDateString(), 'amount' => 1000,
        ])->assertCreated();

        $approvalId = ProjectProfitApproval::query()->where('project_id', $project['id'])->value('id');
        $refundId = ProjectRefund::query()->where('project_id', $project['id'])->value('id');
        $assignmentId = ProjectWriterAssignment::query()->where('project_id', $project['id'])->value('id');
        $this->assertNotNull($approvalId);
        $this->assertNotNull($refundId);
        $this->assertNotNull($assignmentId);

        $this->deleteJson("/api/businesses/{$business->id}/projects/{$project['id']}")
            ->assertOk()
            ->assertJsonPath('message', 'Project permanently deleted.');

        // The project itself is truly gone, not just soft-deleted.
        $this->assertDatabaseMissing('projects', ['id' => $project['id']]);
        $this->assertDatabaseCount('projects', 0);

        // Everything that only ever made sense in the context of this project
        // is gone with it.
        $this->assertDatabaseMissing('project_profit_approvals', ['id' => $approvalId]);
        $this->assertDatabaseMissing('project_refunds', ['id' => $refundId]);
        $this->assertDatabaseMissing('project_writer_assignments', ['id' => $assignmentId]);

        // Real money — the invoice and the payment recorded against it — survives,
        // just unlinked from the now-deleted project.
        $this->assertDatabaseHas('invoices', ['id' => $invoice['id'], 'project_id' => null]);
        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'invoice_id' => $invoice['id']]);
    }

    public function test_a_deleted_project_cannot_be_fetched_again(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-pdp2@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Topic', 'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 5000,
        ])->assertCreated()->json('project');

        $this->deleteJson("/api/businesses/{$business->id}/projects/{$project['id']}")->assertOk();
        $this->getJson("/api/businesses/{$business->id}/projects/{$project['id']}")->assertNotFound();
    }
}
