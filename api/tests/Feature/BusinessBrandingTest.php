<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_business_branding_and_upload_logo(): void
    {
        Storage::fake('public');
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'branding-owner@example.test',
            'password' => 'password',
        ]);
        $business = $this->business($owner);
        $business->memberships()->create([
            'user_id' => $owner->id,
            'role' => BusinessRole::Owner,
            'active' => true,
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson("/api/businesses/{$business->id}", [
            'settings' => [
                'branding' => [
                    'tagline' => 'Learn. Build. Grow.',
                    'primary_color' => '#2457A6',
                    'nav_color' => '#11243E',
                    'accent_color' => '#F3A712',
                    'show_logo_workspace' => true,
                    'show_logo_invoice' => true,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('business.settings.branding.tagline', 'Learn. Build. Grow.')
            ->assertJsonPath('business.settings.branding.primary_color', '#2457A6');

        $response = $this->post("/api/businesses/{$business->id}/branding/logo", [
            'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $path = data_get($business->fresh()->settings, 'branding.logo_path');
        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_employee_cannot_change_business_branding(): void
    {
        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'branding-owner-2@example.test',
            'password' => 'password',
        ]);
        $employee = User::query()->create([
            'name' => 'Employee',
            'email' => 'branding-employee@example.test',
            'password' => 'password',
        ]);
        $business = $this->business($owner);
        $business->memberships()->create([
            'user_id' => $employee->id,
            'role' => BusinessRole::Employee,
            'active' => true,
        ]);

        Sanctum::actingAs($employee);

        $this->patchJson("/api/businesses/{$business->id}", [
            'settings' => ['branding' => ['tagline' => 'Changed']],
        ])->assertForbidden();
    }

    private function business(User $owner): Business
    {
        return Business::query()->create([
            'owner_id' => $owner->id,
            'name' => 'AimersZone',
            'slug' => 'aimerszone-branding',
            'code' => 'AIMB',
            'business_type' => 'service',
            'currency' => 'NPR',
            'invoice_prefix' => 'AIMB',
            'default_tax_rate' => 0,
            'status' => 'active',
        ]);
    }
}
