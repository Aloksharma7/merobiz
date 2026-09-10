<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\ProjectProfitApproval;
use App\Models\ProjectRefund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteMoneyLogsTest extends TestCase
{
    use RefreshDatabase;

    private function installmentBusiness(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Delete Logs Co', 'slug' => 'delete-logs-co-'.uniqid(), 'code' => 'DL'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'installment', 'currency' => 'NPR', 'invoice_prefix' => 'DLC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_a_writer_payment_can_be_undone(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dml1@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);
        $writer = $business->writers()->create(['name' => 'Priya Adhikari', 'active' => true]);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Topic one', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 20000, 'writer_payment_amount' => 6000, 'writer_id' => $writer->id,
        ])->assertCreated()->json('project');
        $this->postJson("/api/businesses/{$business->id}/writers/{$writer->id}/payments", [
            'project_id' => $project['id'], 'paid_on' => today()->toDateString(), 'amount' => 2500,
        ])->assertCreated();
        $paymentId = $writer->payments()->latest('id')->first()->id;

        $this->deleteJson("/api/businesses/{$business->id}/writers/{$writer->id}/payments/{$paymentId}")->assertOk();

        $this->assertDatabaseMissing('writer_payments', ['id' => $paymentId]);
        $this->getJson("/api/businesses/{$business->id}/projects/{$project['id']}")
            ->assertOk()->assertJsonPath('data.writer_due_amount', 6000);
    }

    public function test_a_project_refund_can_be_undone(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dml2@example.test', 'password' => 'password']);
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
        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/refunds", [
            'refunded_on' => today()->toDateString(), 'amount' => 3000,
        ])->assertCreated()->assertJsonPath('refunded_amount', 3000);
        $refundId = ProjectRefund::query()->where('project_id', $project['id'])->latest('id')->first()->id;

        $this->deleteJson("/api/businesses/{$business->id}/projects/{$project['id']}/refunds/{$refundId}")->assertOk();

        $this->assertDatabaseMissing('project_refunds', ['id' => $refundId]);
    }

    public function test_a_profit_approval_can_be_undone_by_a_full_control_owner(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dml3@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Topic one', 'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 20000,
        ])->assertCreated()->json('project');
        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/profit-approvals", [
            'approved_on' => today()->toDateString(), 'amount' => 5000,
        ])->assertCreated()->assertJsonPath('approved_profit_total', 5000);
        $approvalId = ProjectProfitApproval::query()->where('project_id', $project['id'])->latest('id')->first()->id;

        $this->deleteJson("/api/businesses/{$business->id}/projects/{$project['id']}/profit-approvals/{$approvalId}")->assertOk();

        $this->assertDatabaseMissing('project_profit_approvals', ['id' => $approvalId]);
    }

    public function test_a_profit_approval_cannot_be_undone_without_full_control(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dml4@example.test', 'password' => 'password']);
        $coOwner = User::query()->create(['name' => 'Co Owner', 'email' => 'coowner-dml4@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);
        $business->memberships()->create(['user_id' => $coOwner->id, 'role' => BusinessRole::Owner, 'full_control' => false, 'active' => true]);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Topic one', 'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 20000,
        ])->assertCreated()->json('project');
        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/profit-approvals", [
            'approved_on' => today()->toDateString(), 'amount' => 5000,
        ])->assertCreated();
        $approvalId = ProjectProfitApproval::query()->where('project_id', $project['id'])->latest('id')->first()->id;

        Sanctum::actingAs($coOwner);
        $this->deleteJson("/api/businesses/{$business->id}/projects/{$project['id']}/profit-approvals/{$approvalId}")->assertForbidden();
        $this->assertDatabaseHas('project_profit_approvals', ['id' => $approvalId]);
    }

    public function test_an_owner_can_undo_their_own_profit_withdrawal(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dml5@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/profit-withdrawals", [
            'withdrawn_on' => today()->toDateString(), 'amount' => 1500,
        ])->assertCreated();
        $withdrawalId = $business->profitWithdrawals()->latest('id')->first()->id;

        $this->deleteJson("/api/businesses/{$business->id}/profit-withdrawals/{$withdrawalId}")->assertOk();

        $this->assertDatabaseMissing('profit_withdrawals', ['id' => $withdrawalId]);
    }

    public function test_a_non_full_control_owner_cannot_undo_someone_elses_withdrawal(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-dml6@example.test', 'password' => 'password']);
        $coOwner = User::query()->create(['name' => 'Co Owner', 'email' => 'coowner-dml6@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);
        $business->memberships()->create(['user_id' => $coOwner->id, 'role' => BusinessRole::Owner, 'full_control' => false, 'active' => true]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/profit-withdrawals", [
            'withdrawn_on' => today()->toDateString(), 'amount' => 1500,
        ])->assertCreated();
        $withdrawalId = $business->profitWithdrawals()->latest('id')->first()->id;

        Sanctum::actingAs($coOwner);
        $this->deleteJson("/api/businesses/{$business->id}/profit-withdrawals/{$withdrawalId}")->assertForbidden();
        $this->assertDatabaseHas('profit_withdrawals', ['id' => $withdrawalId]);
    }
}
