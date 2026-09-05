<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use App\Services\ProjectWriterAssignmentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WriterProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_writer_profile_lists_their_current_and_past_projects_with_totals(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Enlighten Research', 'slug' => 'enlighten-'.uniqid(), 'code' => 'ER'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'installment', 'currency' => 'NPR', 'invoice_prefix' => 'ERC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $writer = $business->writers()->create(['name' => 'Priya Adhikari', 'active' => true]);
        $otherWriter = $business->writers()->create(['name' => 'Rajan Thapa', 'active' => true]);

        Sanctum::actingAs($owner);
        $current = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Topic one', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 20000, 'writer_payment_amount' => 6000, 'writer_id' => $writer->id,
        ])->assertCreated()->json('project');

        $past = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client B', 'topic' => 'Topic two', 'course' => 'MSc', 'work' => 'Dissertation',
            'deal_amount' => 15000, 'writer_payment_amount' => 4000, 'writer_id' => $writer->id,
        ])->assertCreated()->json('project');

        // Reassign the second project away from the writer, so it becomes a past assignment.
        app(ProjectWriterAssignmentService::class)->assign(
            \App\Models\Project::find($past['id']), $otherWriter->id, CarbonImmutable::now(), null, $owner, $business,
        );

        $unrelated = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client C', 'topic' => 'Topic three', 'course' => 'BBA', 'work' => 'Report',
            'deal_amount' => 8000,
        ])->assertCreated()->json('project');

        $response = $this->getJson("/api/businesses/{$business->id}/writers/{$writer->id}")->assertOk()->json();

        $this->assertSame('Priya Adhikari', $response['writer']['name']);
        $this->assertSame(2, $response['stats']['total_projects']);
        $this->assertSame(1, $response['stats']['current_projects']);
        $this->assertEquals(10000.0, $response['stats']['total_agreed']);
        $this->assertEquals(0.0, $response['stats']['total_paid']);
        $this->assertEquals(6000.0, $response['stats']['total_due']);

        $projectIds = collect($response['projects'])->pluck('id')->all();
        $this->assertContains($current['id'], $projectIds);
        $this->assertContains($past['id'], $projectIds);
        $this->assertNotContains($unrelated['id'], $projectIds);

        $currentRow = collect($response['projects'])->firstWhere('id', $current['id']);
        $pastRow = collect($response['projects'])->firstWhere('id', $past['id']);
        $this->assertTrue($currentRow['is_current']);
        $this->assertEquals(6000.0, $currentRow['writer_due_amount']);
        $this->assertFalse($pastRow['is_current']);
        $this->assertNull($pastRow['writer_due_amount']);
        $this->assertNotNull($pastRow['assigned_to']);
        $this->assertArrayNotHasKey('deal_amount', $currentRow);
    }
}
