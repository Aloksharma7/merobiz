<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectTest extends TestCase
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

    public function test_creating_a_project_requires_an_installment_category_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner1@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Aimers AI', 'slug' => 'aimers-'.uniqid(), 'code' => 'AI'.rand(1000, 9999),
            'business_type' => 'digital_subscription', 'category' => 'standard', 'currency' => 'NPR', 'invoice_prefix' => 'AAI',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Impact of AI', 'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 10000,
        ])->assertStatus(422);
    }

    public function test_a_sale_in_an_installment_business_requires_a_project_and_drives_its_due_amount(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);
        $otherBusiness = $this->installmentBusiness(User::query()->create(['name' => 'Other', 'email' => 'other2@example.test', 'password' => 'password']));

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'client_phone' => '9800000000', 'topic' => 'Impact of AI on education',
            'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 10000, 'writer_payment_amount' => 3000,
        ])->assertCreated()->json('project');

        $this->assertEquals(0.0, $project['collected_amount']);
        $this->assertEquals(10000.0, $project['due_amount']);

        // A sale without a project is rejected for an installment business.
        $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Client A', 'invoice_date' => today()->toDateString(), 'status' => 'issued',
            'discount_amount' => 0,
            'items' => [['description' => 'Thesis writing', 'quantity' => 1, 'unit_price' => 10000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['project_id']);

        // A project belonging to another business is rejected.
        $otherProject = $otherBusiness->projects()->create([
            'created_by' => $owner->id, 'client_name' => 'Foreign', 'topic' => 'x', 'course' => 'x', 'work' => 'x', 'deal_amount' => 1000,
        ]);
        $this->postJson("/api/businesses/{$business->id}/invoices", [
            'customer_name' => 'Client A', 'project_id' => $otherProject->id, 'invoice_date' => today()->toDateString(), 'status' => 'issued',
            'discount_amount' => 0,
            'items' => [['description' => 'Thesis writing', 'quantity' => 1, 'unit_price' => 10000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['project_id']);

        // A sale tied to this project succeeds and its client details are picked up from the project.
        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $project['id'], 'invoice_date' => today()->toDateString(), 'status' => 'issued',
            'discount_amount' => 0,
            'items' => [['description' => 'Thesis writing', 'quantity' => 1, 'unit_price' => 10000, 'unit_cost' => 4000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $this->assertSame('Client A', $invoice['customer_name']);
        $this->assertSame('9800000000', $invoice['customer_phone']);

        $due = fn () => $this->getJson("/api/businesses/{$business->id}/projects/{$project['id']}")->json('data.due_amount');
        $this->assertEquals(10000.0, $due());

        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 4000, 'method' => 'bank_transfer',
        ])->assertCreated();
        $this->assertEquals(6000.0, $due());

        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 6000, 'method' => 'bank_transfer',
        ])->assertCreated();
        $this->assertEquals(0.0, $due());

        // A further sale beyond the project's remaining due amount is rejected.
        $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $project['id'], 'invoice_date' => today()->toDateString(), 'status' => 'issued',
            'discount_amount' => 0,
            'items' => [['description' => 'Extra work', 'quantity' => 1, 'unit_price' => 500, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['amount']);
    }

    public function test_each_sale_is_its_own_fully_paid_invoice_and_due_tracks_against_the_deal_total(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2c@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client D', 'topic' => 'Financial literacy', 'course' => 'BBA', 'work' => 'Report', 'deal_amount' => 22000,
        ])->assertCreated()->json('project');

        $payProject = function (float $amount) use ($business, $project) {
            $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
                'project_id' => $project['id'], 'invoice_date' => today()->toDateString(), 'status' => 'issued',
                'discount_amount' => 0,
                'items' => [['description' => 'Payment', 'quantity' => 1, 'unit_price' => $amount, 'discount_amount' => 0, 'tax_rate' => 0]],
            ])->assertCreated()->json('invoice');

            $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
                'payment_date' => today()->toDateString(), 'amount' => $amount, 'method' => 'bank_transfer',
            ])->assertCreated();

            return $this->getJson("/api/businesses/{$business->id}/projects/{$project['id']}")->json('data');
        };

        $afterFirst = $payProject(5000);
        $this->assertEquals(5000.0, $afterFirst['collected_amount']);
        $this->assertEquals(17000.0, $afterFirst['due_amount']);

        $afterSecond = $payProject(9000);
        $this->assertEquals(14000.0, $afterSecond['collected_amount']);
        $this->assertEquals(8000.0, $afterSecond['due_amount']);

        $afterThird = $payProject(8000);
        $this->assertEquals(22000.0, $afterThird['collected_amount']);
        $this->assertEquals(0.0, $afterThird['due_amount']);
    }

    public function test_projects_can_be_filtered_by_status_and_writer(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2d@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);
        $writerA = $business->writers()->create(['name' => 'Writer A', 'active' => true]);
        $writerB = $business->writers()->create(['name' => 'Writer B', 'active' => true]);

        Sanctum::actingAs($owner);
        $started = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client Started', 'topic' => 'Topic one', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 10000, 'writer_id' => $writerA->id,
        ])->assertCreated()->json('project');
        $submitted = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client Submitted', 'topic' => 'Topic two', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 12000, 'work_status' => 'submitted', 'writer_id' => $writerB->id,
        ])->assertCreated()->json('project');

        $byStatus = $this->getJson("/api/businesses/{$business->id}/projects?status=submitted")->assertOk()->json('data');
        $this->assertCount(1, $byStatus);
        $this->assertSame($submitted['id'], $byStatus[0]['id']);

        $byWriter = $this->getJson("/api/businesses/{$business->id}/projects?writer_id={$writerA->id}")->assertOk()->json('data');
        $this->assertCount(1, $byWriter);
        $this->assertSame($started['id'], $byWriter[0]['id']);
    }

    public function test_installment_business_invoice_cost_and_commission_do_not_count_toward_profit_automatically(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2b@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Impact of AI on education', 'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 10000,
        ])->assertCreated()->json('project');

        $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $project['id'], 'invoice_date' => today()->toDateString(), 'status' => 'issued',
            'discount_amount' => 0,
            'items' => [['description' => 'Thesis writing', 'quantity' => 1, 'unit_price' => 10000, 'unit_cost' => 4000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated();

        $dashboard = app(DashboardService::class);
        $metrics = $dashboard->metrics($business->fresh(), CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()->endOfMonth());

        $this->assertSame(10000.0, $metrics['net_sales']);
        $this->assertSame(0.0, $metrics['cost_of_sales']);
        $this->assertSame(0.0, $metrics['gross_profit']);
        $this->assertSame(0.0, $metrics['net_profit']);

        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/profit-approvals", [
            'approved_on' => today()->toDateString(), 'amount' => 6000,
        ])->assertCreated();

        $after = $dashboard->metrics($business->fresh(), CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()->endOfMonth());
        $this->assertSame(6000.0, $after['net_profit']);
    }

    public function test_approving_project_profit_only_counts_once_approved_and_requires_full_control(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner3@example.test', 'password' => 'password']);
        $partner = User::query()->create(['name' => 'Partner', 'email' => 'partner3@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);
        $business->memberships()->create(['user_id' => $partner->id, 'role' => BusinessRole::Owner, 'full_control' => false, 'active' => true]);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client B', 'topic' => 'Data analysis for retail', 'course' => 'MSc', 'work' => 'Dissertation', 'deal_amount' => 20000,
        ])->assertCreated()->json('project');

        $dashboard = app(DashboardService::class);
        $start = CarbonImmutable::today()->startOfMonth();
        $end = CarbonImmutable::today()->endOfMonth();

        $before = $dashboard->metrics($business->fresh(), $start, $end);
        $this->assertSame(0.0, $before['project_profit']);
        $this->assertSame(0.0, $before['net_profit']);

        Sanctum::actingAs($partner);
        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/profit-approvals", [
            'approved_on' => today()->toDateString(), 'amount' => 12000,
        ])->assertStatus(403);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/profit-approvals", [
            'approved_on' => today()->toDateString(), 'amount' => 12000,
        ])->assertCreated()->assertJsonPath('approved_profit_total', 12000);

        $after = $dashboard->metrics($business->fresh(), $start, $end);
        $this->assertSame(12000.0, $after['project_profit']);
        $this->assertSame(12000.0, $after['net_profit']);
    }

    public function test_project_writer_assignment_tracks_dated_reassignment_history(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner4@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);
        $writerA = $business->writers()->create(['name' => 'Writer A', 'active' => true]);
        $writerB = $business->writers()->create(['name' => 'Writer B', 'active' => true]);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client C', 'topic' => 'Market research', 'course' => 'BBA', 'work' => 'Report', 'deal_amount' => 8000,
            'writer_id' => $writerA->id,
        ])->assertCreated()->json('project');

        $this->assertSame('Writer A', $project['current_writer']['name']);

        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/writer", [
            'writer_id' => $writerB->id, 'date' => today()->toDateString(),
        ])->assertCreated();

        $history = $this->getJson("/api/businesses/{$business->id}/projects/{$project['id']}/writer")
            ->assertOk()->json('data');

        $this->assertCount(2, $history);
        $current = collect($history)->firstWhere('current', true);
        $past = collect($history)->firstWhere('current', false);
        $this->assertSame('Writer B', $current['writer']['name']);
        $this->assertSame('Writer A', $past['writer']['name']);
        $this->assertNotNull($past['assigned_to']);
    }

    public function test_standard_category_business_requires_product_type_on_creation(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner5@example.test', 'password' => 'password']);
        Sanctum::actingAs($owner);

        $this->postJson('/api/businesses', [
            'name' => 'New Shop', 'business_type' => 'product', 'category' => 'standard',
            'ownership_percent' => 100,
        ])->assertUnprocessable()->assertJsonValidationErrors(['product_type']);

        $this->postJson('/api/businesses', [
            'name' => 'New Shop', 'business_type' => 'product', 'category' => 'standard', 'product_type' => 'physical',
            'ownership_percent' => 100,
        ])->assertCreated();

        $this->postJson('/api/businesses', [
            'name' => 'Thesis Helpers', 'business_type' => 'service', 'category' => 'installment',
            'ownership_percent' => 100,
        ])->assertCreated();
    }

    public function test_listing_projects_does_not_issue_a_query_per_project_for_the_current_writer(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-list@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $writer = $this->postJson("/api/businesses/{$business->id}/writers", ['name' => 'Rita Writer'])
            ->assertCreated()->json('writer');

        for ($i = 0; $i < 10; $i++) {
            $this->postJson("/api/businesses/{$business->id}/projects", [
                'client_name' => 'Client '.$i, 'topic' => 'Topic '.$i, 'course' => 'MBA', 'work' => 'Thesis',
                'deal_amount' => 10000, 'writer_id' => $writer['id'],
            ])->assertCreated();
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->getJson("/api/businesses/{$business->id}/projects")->assertOk()->assertJsonCount(10, 'data');
        $queryCount = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // Each project's current-writer, collected/due, and profit totals used to
        // re-query the database per project even with writerAssignments eager-loaded
        // (56 queries for 10 projects before this was fixed). This stays flat now.
        $this->assertLessThan(15, $queryCount, "Listing 10 projects issued {$queryCount} queries — check for a reintroduced N+1.");
    }
}
