<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerProjectProfileTest extends TestCase
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

    public function test_creating_a_project_auto_creates_and_reuses_a_matching_customer(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $first = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Sushant Basnet', 'client_phone' => '9800000000', 'topic' => 'Topic one',
            'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 20000,
        ])->assertCreated()->json('project');

        $this->assertNotNull($first['customer_id']);

        $second = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Sushant Basnet', 'topic' => 'Topic two',
            'course' => 'MBA', 'work' => 'Dissertation', 'deal_amount' => 15000,
        ])->assertCreated()->json('project');

        // Same client name reuses the same customer record instead of creating a duplicate.
        $this->assertSame($first['customer_id'], $second['customer_id']);

        $customers = $this->getJson("/api/businesses/{$business->id}/customers")->assertOk()->json('data');
        $this->assertCount(1, $customers);
        $this->assertSame('Sushant Basnet', $customers[0]['name']);
    }

    public function test_creating_a_project_can_link_to_an_explicitly_selected_customer(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);
        $customer = $business->customers()->create(['name' => 'Kiran Bhusal', 'phone' => '9800000001', 'opening_balance' => 0, 'active' => true]);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'customer_id' => $customer->id, 'client_name' => 'Kiran Bhusal', 'topic' => 'Topic',
            'course' => 'MSc', 'work' => 'Dissertation', 'deal_amount' => 10000,
        ])->assertCreated()->json('project');

        $this->assertSame($customer->id, $project['customer_id']);
    }

    public function test_customer_profile_shows_their_project_history_and_totals_for_installment_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner3@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $done = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Sarita Tamang', 'topic' => 'Topic one', 'course' => 'BBA', 'work' => 'Report',
            'deal_amount' => 22000, 'work_status' => 'approved',
        ])->assertCreated()->json('project');

        $ongoing = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Sarita Tamang', 'topic' => 'Topic two', 'course' => 'BBA', 'work' => 'Assignment',
            'deal_amount' => 8000,
        ])->assertCreated()->json('project');

        $invoice = $this->postJson("/api/businesses/{$business->id}/invoices", [
            'project_id' => $done['id'], 'invoice_date' => today()->toDateString(), 'status' => 'issued',
            'discount_amount' => 0,
            'items' => [['description' => 'Payment', 'quantity' => 1, 'unit_price' => 22000, 'discount_amount' => 0, 'tax_rate' => 0]],
        ])->assertCreated()->json('invoice');
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice['id']}/payments", [
            'payment_date' => today()->toDateString(), 'amount' => 22000, 'method' => 'bank_transfer',
        ])->assertCreated();

        $customerId = $done['customer_id'];
        $response = $this->getJson("/api/businesses/{$business->id}/customers/{$customerId}")->assertOk()->json();

        $this->assertSame('Sarita Tamang', $response['customer']['name']);
        $this->assertSame(2, $response['stats']['total_projects']);
        $this->assertSame(1, $response['stats']['completed_projects']);
        $this->assertSame(1, $response['stats']['in_progress_projects']);
        $this->assertEquals(30000.0, $response['stats']['total_deal_amount']);

        $projectIds = collect($response['projects'])->pluck('id')->all();
        $this->assertContains($done['id'], $projectIds);
        $this->assertContains($ongoing['id'], $projectIds);

        $this->assertCount(1, $response['payments']);
        $this->assertEquals(22000.0, $response['payments'][0]['amount']);
        $this->assertSame($done['id'], $response['payments'][0]['project_id']);
        $this->assertSame('Topic one', $response['payments'][0]['project_topic']);
    }
}
