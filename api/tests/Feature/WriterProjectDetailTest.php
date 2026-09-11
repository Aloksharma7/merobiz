<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WriterProjectDetailTest extends TestCase
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

    public function test_a_writer_sees_the_full_topic_and_their_own_pay_but_no_client_money_figures(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-wpd1@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $writer = $this->postJson("/api/businesses/{$business->id}/writers", [
            'name' => 'Rita Writer', 'email' => 'rita-wpd1@example.test', 'password' => 'temporary1',
        ])->assertCreated()->json('writer');

        $longTopic = trim(str_repeat('Impact of remote work on organisational culture ', 3));
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'client_phone' => '9800000000', 'topic' => $longTopic, 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 20000, 'writer_payment_amount' => 6000, 'writer_id' => $writer['id'],
        ])->assertCreated()->json('project');

        $this->postJson("/api/businesses/{$business->id}/writers/{$writer['id']}/payments", [
            'project_id' => $project['id'], 'paid_on' => today()->toDateString(), 'amount' => 2000,
        ])->assertCreated();

        $writerUser = User::query()->where('email', 'rita-wpd1@example.test')->firstOrFail();
        Sanctum::actingAs($writerUser);

        $response = $this->getJson("/api/writer/projects/{$project['id']}")->assertOk()->json();

        $this->assertSame($longTopic, $response['topic']);
        $this->assertSame('Client A', $response['client_name']);
        $this->assertSame('9800000000', $response['client_phone']);
        $this->assertTrue($response['is_current']);
        $this->assertEquals(6000.0, $response['writer_payment_amount']);
        $this->assertEquals(2000.0, $response['writer_paid_amount']);
        $this->assertEquals(4000.0, $response['writer_due_amount']);

        // Never the business's own money figures for this file.
        $this->assertArrayNotHasKey('deal_amount', $response);
        $this->assertArrayNotHasKey('collected_amount', $response);
        $this->assertArrayNotHasKey('due_amount', $response);
        $this->assertArrayNotHasKey('profit_approvals', $response);
        $this->assertArrayNotHasKey('refunds', $response);
    }

    public function test_a_writer_cannot_view_a_file_they_were_never_assigned_to(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-wpd2@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $writer = $this->postJson("/api/businesses/{$business->id}/writers", [
            'name' => 'Rita Writer', 'email' => 'rita-wpd2@example.test', 'password' => 'temporary1',
        ])->assertCreated()->json('writer');
        $otherProject = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client B', 'topic' => 'Not assigned to Rita', 'course' => 'MBA', 'work' => 'Thesis', 'deal_amount' => 5000,
        ])->assertCreated()->json('project');

        $writerUser = User::query()->where('email', 'rita-wpd2@example.test')->firstOrFail();
        Sanctum::actingAs($writerUser);

        $this->getJson("/api/writer/projects/{$otherProject['id']}")->assertNotFound();
    }

    public function test_a_writer_can_still_view_a_file_after_being_reassigned_away_from_it(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-wpd3@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $writer = $this->postJson("/api/businesses/{$business->id}/writers", [
            'name' => 'Rita Writer', 'email' => 'rita-wpd3@example.test', 'password' => 'temporary1',
        ])->assertCreated()->json('writer');
        $otherWriter = $this->postJson("/api/businesses/{$business->id}/writers", ['name' => 'Other Writer'])
            ->assertCreated()->json('writer');
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client C', 'topic' => 'Reassigned file', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 5000, 'writer_id' => $writer['id'],
        ])->assertCreated()->json('project');
        $this->postJson("/api/businesses/{$business->id}/projects/{$project['id']}/writer", [
            'writer_id' => $otherWriter['id'], 'date' => today()->toDateString(),
        ])->assertCreated();

        $writerUser = User::query()->where('email', 'rita-wpd3@example.test')->firstOrFail();
        Sanctum::actingAs($writerUser);

        $response = $this->getJson("/api/writer/projects/{$project['id']}")->assertOk()->json();
        $this->assertFalse($response['is_current']);
        $this->assertNull($response['writer_due_amount']);
    }
}
