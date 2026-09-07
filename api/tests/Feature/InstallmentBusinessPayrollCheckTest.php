<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ProjectWriterAssignmentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstallmentBusinessPayrollCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_payroll_and_writer_payments_both_reduce_installment_business_profit(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-inst@example.test', 'password' => 'password']);
        $staff = User::query()->create(['name' => 'Staff', 'email' => 'staff-inst@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Thesis Co', 'slug' => 'thesis-co-'.uniqid(), 'code' => 'TC'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'installment', 'currency' => 'NPR', 'invoice_prefix' => 'TC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $staffMembership = $business->memberships()->create([
            'user_id' => $staff->id, 'role' => BusinessRole::Employee, 'active' => true,
            'pay_type' => 'fixed_salary', 'salary_amount' => 5000, 'joined_at' => today()->toDateString(),
        ]);

        $writer = $business->writers()->create(['name' => 'Writer One', 'active' => true]);
        $project = $business->projects()->create([
            'created_by' => $owner->id, 'client_name' => 'Client A', 'topic' => 'Topic', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 20000, 'writer_payment_amount' => 6000,
        ]);
        app(ProjectWriterAssignmentService::class)->assign($project, $writer->id, CarbonImmutable::today(), null, $owner, $business);

        $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $project->id, 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['description' => 'Payment', 'quantity' => 1, 'unit_price' => 20000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);
        Sanctum::actingAs($owner);

        // Admin approves the project's gross contribution (the deal amount) — writer
        // payment is no longer something they need to subtract themselves, since the
        // system now deducts actual writer payments automatically, same as payroll.
        $this->postJson("/api/businesses/{$business->id}/projects/{$project->id}/profit-approvals", [
            'approved_on' => today()->toDateString(), 'amount' => 20000, 'notes' => 'Deal amount, before writer payment',
        ])->assertCreated();

        $this->postJson("/api/businesses/{$business->id}/writers/{$writer->id}/payments", [
            'project_id' => $project->id, 'paid_on' => today()->toDateString(), 'amount' => 6000,
        ])->assertCreated();

        $this->postJson("/api/businesses/{$business->id}/team/{$staffMembership->id}/salary/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 5000, 'method' => 'bank_transfer',
        ])->assertCreated();

        $dashboard = app(DashboardService::class);
        $metrics = $dashboard->metrics($business->fresh(), CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()->endOfMonth());

        $this->assertSame(5000.0, $metrics['payroll_cost']);
        $this->assertSame(6000.0, $metrics['writer_cost']);
        // Net profit = approved gross project profit (20000) minus real staff payroll
        // (5000) minus the real writer payment (6000) — both now real, automatic costs.
        $this->assertSame(9000.0, $metrics['net_profit']);
    }
}
