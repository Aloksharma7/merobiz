<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WriterPaymentTest extends TestCase
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

    public function test_recording_writer_payments_reduces_the_files_writer_due_and_rejects_overpayment(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);
        $writer = $business->writers()->create(['name' => 'Priya Adhikari', 'active' => true]);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Topic one', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 20000, 'writer_payment_amount' => 6000, 'writer_id' => $writer->id,
        ])->assertCreated()->json('project');

        $this->postJson("/api/businesses/{$business->id}/writers/{$writer->id}/payments", [
            'project_id' => $project['id'], 'paid_on' => today()->toDateString(), 'amount' => 2500,
        ])->assertCreated()->assertJsonPath('writer_due_amount', 3500);

        $this->postJson("/api/businesses/{$business->id}/writers/{$writer->id}/payments", [
            'project_id' => $project['id'], 'paid_on' => today()->toDateString(), 'amount' => 4000,
        ])->assertUnprocessable()->assertJsonValidationErrors(['amount']);

        $this->postJson("/api/businesses/{$business->id}/writers/{$writer->id}/payments", [
            'project_id' => $project['id'], 'paid_on' => today()->toDateString(), 'amount' => 3500,
        ])->assertCreated()->assertJsonPath('writer_due_amount', 0);

        $profile = $this->getJson("/api/businesses/{$business->id}/writers/{$writer->id}")->assertOk()->json();
        $this->assertEquals(6000.0, $profile['stats']['total_paid']);
        $this->assertEquals(0.0, $profile['stats']['total_due']);
    }

    public function test_a_writer_cannot_be_paid_for_a_file_they_were_never_assigned_to(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);
        $writer = $business->writers()->create(['name' => 'Priya Adhikari', 'active' => true]);
        $unassignedProject = $business->projects()->create([
            'created_by' => $owner->id, 'client_name' => 'Client X', 'started_on' => today()->toDateString(),
            'topic' => 'x', 'course' => 'x', 'work' => 'x', 'deal_amount' => 5000, 'writer_payment_amount' => 1000,
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/writers/{$writer->id}/payments", [
            'project_id' => $unassignedProject->id, 'paid_on' => today()->toDateString(), 'amount' => 500,
        ])->assertStatus(422);
    }

    public function test_writer_payments_can_be_filtered_by_month(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner3@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);
        $writer = $business->writers()->create(['name' => 'Priya Adhikari', 'active' => true]);

        Sanctum::actingAs($owner);
        $project = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Topic one', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 20000, 'writer_payment_amount' => 10000, 'writer_id' => $writer->id,
        ])->assertCreated()->json('project');

        $lastMonth = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth()->addDays(3)->toDateString();
        $this->postJson("/api/businesses/{$business->id}/writers/{$writer->id}/payments", [
            'project_id' => $project['id'], 'paid_on' => $lastMonth, 'amount' => 4000,
        ])->assertCreated();
        $this->postJson("/api/businesses/{$business->id}/writers/{$writer->id}/payments", [
            'project_id' => $project['id'], 'paid_on' => today()->toDateString(), 'amount' => 3000,
        ])->assertCreated();

        $thisMonth = $this->getJson("/api/businesses/{$business->id}/writers/{$writer->id}")->assertOk()->json();
        $this->assertEquals(3000.0, $thisMonth['stats']['period_paid']);
        $this->assertCount(1, $thisMonth['payments']);
        $this->assertEquals(7000.0, $thisMonth['stats']['total_paid']);

        $start = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $end = CarbonImmutable::now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $lastMonthResponse = $this->getJson("/api/businesses/{$business->id}/writers/{$writer->id}?start={$start}&end={$end}")->assertOk()->json();
        $this->assertEquals(4000.0, $lastMonthResponse['stats']['period_paid']);
        $this->assertCount(1, $lastMonthResponse['payments']);
    }
}
