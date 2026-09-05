<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WriterLoginTest extends TestCase
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

    public function test_owner_can_grant_a_writer_login_and_it_reports_writer_workspace_mode(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $writer = $this->postJson("/api/businesses/{$business->id}/writers", [
            'name' => 'Rita Writer', 'email' => 'rita@example.test', 'password' => 'temporary1',
        ])->assertCreated()->json('writer');

        $this->assertTrue($writer['has_login']);

        $writerUser = User::query()->where('email', 'rita@example.test')->firstOrFail();
        Sanctum::actingAs($writerUser);

        $me = $this->getJson('/api/auth/me')->assertOk()->json();
        $this->assertSame('writer', $me['workspace']['mode']);
        $this->assertSame($business->id, $me['workspace']['business_id']);
        $this->assertSame($writer['id'], $me['workspace']['writer_id']);
    }

    public function test_writer_dashboard_shows_only_their_own_projects_and_totals(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner2@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $writer = $this->postJson("/api/businesses/{$business->id}/writers", [
            'name' => 'Sagar Writer', 'email' => 'sagar@example.test', 'password' => 'temporary1',
        ])->assertCreated()->json('writer');
        $otherWriter = $this->postJson("/api/businesses/{$business->id}/writers", ['name' => 'Other Writer'])
            ->assertCreated()->json('writer');

        $mine = $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client A', 'topic' => 'My topic', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 20000, 'writer_payment_amount' => 8000, 'writer_id' => $writer['id'],
        ])->assertCreated()->json('project');

        $this->postJson("/api/businesses/{$business->id}/projects", [
            'client_name' => 'Client B', 'topic' => 'Not mine', 'course' => 'MBA', 'work' => 'Thesis',
            'deal_amount' => 5000, 'writer_id' => $otherWriter['id'],
        ])->assertCreated();

        $this->postJson("/api/businesses/{$business->id}/writers/{$writer['id']}/payments", [
            'project_id' => $mine['id'], 'paid_on' => today()->toDateString(), 'amount' => 3000,
        ])->assertCreated();

        $writerUser = User::query()->where('email', 'sagar@example.test')->firstOrFail();
        Sanctum::actingAs($writerUser);

        $response = $this->getJson('/api/writer/dashboard')->assertOk()->json();

        $this->assertSame('Sagar Writer', $response['writer']['name']);
        $this->assertSame(1, $response['stats']['total_projects']);
        $this->assertEquals(3000.0, $response['stats']['total_paid']);
        $this->assertEquals(5000.0, $response['stats']['total_due']);

        $topics = collect($response['projects'])->pluck('topic')->all();
        $this->assertSame(['My topic'], $topics);
    }

    public function test_a_user_with_no_linked_writer_gets_forbidden_from_the_writer_dashboard(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner3@example.test', 'password' => 'password']);

        Sanctum::actingAs($owner);
        $this->getJson('/api/writer/dashboard')->assertForbidden();
    }

    public function test_setting_a_password_without_an_email_is_rejected(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner4@example.test', 'password' => 'password']);
        $business = $this->installmentBusiness($owner);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/writers", [
            'name' => 'No Email Writer', 'password' => 'temporary1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }
}
