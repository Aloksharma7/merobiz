<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessWriterPaymentsListTest extends TestCase
{
    use RefreshDatabase;

    private function installmentBusiness(User $owner): Business
    {
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Writer List Co', 'slug' => 'writer-list-co-'.uniqid(), 'code' => 'WL'.rand(1000, 9999),
            'business_type' => 'service', 'category' => 'installment', 'currency' => 'NPR', 'invoice_prefix' => 'WLC',
            'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        return $business;
    }

    public function test_an_admin_can_list_every_writer_payment_across_the_business(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-wpl1@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);
        $writerOne = $business->writers()->create(['name' => 'Priya Adhikari', 'active' => true]);
        $writerTwo = $business->writers()->create(['name' => 'Rajan Shrestha', 'active' => true]);

        Sanctum::actingAs($owner);
        $projectOne = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'Topic one', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 20000, 'writer_payment_amount' => 6000, 'writer_id' => $writerOne->id,
        ])->assertCreated()->json('project');
        $projectTwo = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client B', 'topic' => 'Topic two', 'course' => 'BBA', 'work' => 'Report',
            'deal_amount' => 15000, 'writer_payment_amount' => 4000, 'writer_id' => $writerTwo->id,
        ])->assertCreated()->json('project');

        $this->postJson("/api/businesses/{$business->id}/writers/{$writerOne->id}/payments", [
            'project_id' => $projectOne['id'], 'paid_on' => today()->toDateString(), 'amount' => 2500,
        ])->assertCreated();
        $this->postJson("/api/businesses/{$business->id}/writers/{$writerTwo->id}/payments", [
            'project_id' => $projectTwo['id'], 'paid_on' => today()->toDateString(), 'amount' => 1500,
        ])->assertCreated();

        $response = $this->getJson("/api/businesses/{$business->id}/writer-payments")->assertOk();
        $names = collect($response->json('data'))->pluck('writer_name')->all();
        $this->assertCount(2, $response->json('data'));
        $this->assertContains('Priya Adhikari', $names);
        $this->assertContains('Rajan Shrestha', $names);
    }

    public function test_a_business_without_writers_manage_cannot_list_writer_payments(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-wpl2@example.test', 'password' => 'password']);
        $outsider = User::query()->create(['name' => 'Outsider', 'email' => 'outsider-wpl2@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/businesses/{$business->id}/writer-payments")->assertForbidden();
    }
}
