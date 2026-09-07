<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfitWithdrawalListTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_owner_can_list_their_withdrawals_but_an_employee_cannot(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-pw@example.test', 'password' => 'password']);
        $employee = User::query()->create(['name' => 'Employee', 'email' => 'employee-pw@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Withdrawal Co', 'slug' => 'withdrawal-co-'.uniqid(), 'code' => 'WC'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'WC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'full_control' => true, 'active' => true]);
        $business->memberships()->create(['user_id' => $employee->id, 'role' => BusinessRole::Employee, 'active' => true]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/businesses/{$business->id}/profit-withdrawals", [
            'withdrawn_on' => today()->toDateString(), 'amount' => 2000, 'notes' => 'Personal use',
        ])->assertCreated();

        $response = $this->getJson("/api/businesses/{$business->id}/profit-withdrawals")->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.amount', 2000);
        $response->assertJsonPath('data.0.notes', 'Personal use');
        $response->assertJsonPath('data.0.user_name', 'Owner');

        Sanctum::actingAs($employee);
        $this->getJson("/api/businesses/{$business->id}/profit-withdrawals")->assertForbidden();
    }
}
