<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBusinessBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_read_a_businesss_basic_brand_without_logging_in(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-pbb1@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'TechChamp Tools', 'slug' => 'techchamp-tools-'.uniqid(), 'code' => 'TCT'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'TCT', 'default_tax_rate' => 0, 'status' => 'active',
            'settings' => ['branding' => ['primary_color' => '#135f48', 'nav_color' => '#0b3c31']],
        ]);

        $this->getJson("/api/public/businesses/{$business->id}/brand")
            ->assertOk()
            ->assertJsonPath('name', 'TechChamp Tools')
            ->assertJsonPath('logo_url', null)
            ->assertJsonPath('primary_color', '#135f48')
            ->assertJsonPath('nav_color', '#0b3c31');
    }

    public function test_it_never_exposes_anything_beyond_name_colors_and_logo(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-pbb2@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Private Co', 'slug' => 'private-co-'.uniqid(), 'code' => 'PRV'.rand(1000, 9999),
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'PRV', 'default_tax_rate' => 0, 'status' => 'active',
        ]);

        $response = $this->getJson("/api/public/businesses/{$business->id}/brand")->assertOk();
        $this->assertEqualsCanonicalizing(['name', 'code', 'logo_url', 'primary_color', 'nav_color'], array_keys($response->json()));
    }
}
