<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoOwnerControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_founder_gets_full_control_automatically_and_co_owner_does_not(): void
    {
        $founder = $this->user('Founder', 'founder@example.test');
        Sanctum::actingAs($founder);

        $business = $this->postJson('/api/businesses', [
            'name' => 'AimersZone', 'business_type' => 'service', 'category' => 'standard', 'product_type' => 'digital', 'ownership_percent' => 50,
        ])->assertCreated()->json('business');

        $partner = $this->user('Partner', 'partner@example.test');
        $partnerMembership = $this->postJson("/api/businesses/{$business['id']}/team", [
            'name' => $partner->name, 'email' => $partner->email, 'role' => 'owner',
        ])->assertCreated()->json('member');

        $this->assertFalse($partnerMembership['full_control'], 'A co-owner should not get full control by default.');

        // The co-owner cannot change ownership stakes...
        Sanctum::actingAs($partner);
        $this->postJson("/api/businesses/{$business['id']}/ownerships", [
            'user_id' => $partner->id, 'ownership_percent' => 50, 'profit_share_percent' => 50,
            'effective_from' => today()->toDateString(),
        ])->assertForbidden();

        // ...nor assign the owner role to someone else...
        $another = $this->user('Another', 'another@example.test');
        $this->postJson("/api/businesses/{$business['id']}/team", [
            'name' => $another->name, 'email' => $another->email, 'role' => 'owner',
        ])->assertForbidden();

        // ...nor touch the founder's own membership.
        $founderMembershipId = $this->getJson("/api/businesses/{$business['id']}/team")
            ->json('data.0.id');
        $this->patchJson("/api/businesses/{$business['id']}/team/{$founderMembershipId}", ['active' => false])
            ->assertForbidden();

        // The founder can grant the co-owner full control.
        Sanctum::actingAs($founder);
        $this->patchJson("/api/businesses/{$business['id']}/team/{$partnerMembership['id']}", ['full_control' => true])
            ->assertOk()
            ->assertJsonPath('member.full_control', true);

        // Now the (formerly reduced) co-owner can manage ownership stakes.
        Sanctum::actingAs($partner);
        $this->postJson("/api/businesses/{$business['id']}/ownerships", [
            'user_id' => $partner->id, 'ownership_percent' => 50, 'profit_share_percent' => 50,
            'effective_from' => today()->toDateString(),
        ])->assertCreated();
    }

    public function test_a_plain_admin_cannot_demote_or_deactivate_an_owner(): void
    {
        $founder = $this->user('Founder', 'founder-admin-test@example.test');
        Sanctum::actingAs($founder);

        $business = $this->postJson('/api/businesses', [
            'name' => 'AimersZone', 'business_type' => 'service', 'category' => 'standard', 'product_type' => 'digital', 'ownership_percent' => 50,
        ])->assertCreated()->json('business');

        $partner = $this->user('Partner', 'partner-admin-test@example.test');
        $partnerMembership = $this->postJson("/api/businesses/{$business['id']}/team", [
            'name' => $partner->name, 'email' => $partner->email, 'role' => 'owner',
        ])->assertCreated()->json('member');

        $admin = $this->user('Admin', 'admin-admin-test@example.test');
        $this->postJson("/api/businesses/{$business['id']}/team", [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'admin',
        ])->assertCreated();

        Sanctum::actingAs($admin);
        $this->patchJson("/api/businesses/{$business['id']}/team/{$partnerMembership['id']}", ['active' => false])
            ->assertForbidden();
        $this->patchJson("/api/businesses/{$business['id']}/team/{$partnerMembership['id']}", ['role' => 'employee'])
            ->assertForbidden();

        // Ordinary fields (not touching ownership) remain editable by an admin.
        $this->patchJson("/api/businesses/{$business['id']}/team/{$partnerMembership['id']}", ['title' => 'Managing Partner'])
            ->assertOk();
    }

    private function user(string $name, string $email): User
    {
        return User::query()->create(['name' => $name, 'email' => $email, 'password' => 'password123']);
    }
}
