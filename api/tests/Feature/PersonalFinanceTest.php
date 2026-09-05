<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_income_sources_entries_and_expenses(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-personal@example.test', 'password' => 'password123']);
        Sanctum::actingAs($owner);

        $source = $this->postJson('/api/personal/income-sources', [
            'name' => 'Nabil Bank savings', 'type' => 'bank', 'notes' => 'Interest income',
        ])->assertCreated()->json('source');

        $this->postJson('/api/personal/income-entries', [
            'source_id' => $source['id'], 'entry_date' => today()->toDateString(), 'amount' => 500,
        ])->assertCreated();

        $this->postJson('/api/personal/expenses', [
            'category' => 'Groceries', 'expense_date' => today()->toDateString(), 'amount' => 120, 'payment_method' => 'cash',
        ])->assertCreated();

        $this->getJson('/api/personal/overview')
            ->assertOk()
            ->assertJsonPath('summary.other_income', 500)
            ->assertJsonPath('summary.total_expenses', 120)
            ->assertJsonPath('summary.net_balance', 380);

        // Another user's income sources are never visible.
        $stranger = User::query()->create(['name' => 'Stranger', 'email' => 'stranger-personal@example.test', 'password' => 'password123']);
        Sanctum::actingAs($stranger);
        $this->getJson('/api/personal/income-sources')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/personal/income-sources/{$source['id']}", ['name' => 'Hijacked'])->assertNotFound();
    }

    public function test_business_profit_received_is_pulled_into_the_overview(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-profit@example.test', 'password' => 'password123']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'AimersZone', 'slug' => 'aimerszone-personal', 'code' => 'AIMP',
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'AIMP', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/profit-withdrawals", [
            'withdrawn_on' => today()->toDateString(), 'amount' => 300,
        ])->assertCreated();

        $response = $this->getJson('/api/personal/overview')->assertOk();
        $response->assertJsonPath('summary.business_profit_received', 300);
        $response->assertJsonPath('summary.total_income', 300);
        $response->assertJsonPath('recent_profit.0.business_name', 'AimersZone');
    }
}
