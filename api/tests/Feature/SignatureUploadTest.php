<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SignatureUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_upload_view_and_remove_their_own_signature(): void
    {
        Storage::fake('public');
        $user = User::query()->create(['name' => 'Signer', 'email' => 'signer@example.test', 'password' => 'password']);

        Sanctum::actingAs($user);
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('has_signature', false)->assertJsonPath('signature_url', null);

        $file = UploadedFile::fake()->image('signature.png', 200, 80);
        $response = $this->postJson('/api/profile/signature', ['signature' => $file])->assertOk();
        $response->assertJsonPath('user.has_signature', true);
        $this->assertNotNull($response->json('user.signature_url'));

        $path = $user->fresh()->signature_path;
        Storage::disk('public')->assertExists($path);

        $this->get("/api/public/users/{$user->id}/signature")->assertOk();

        $this->deleteJson('/api/profile/signature')->assertOk()->assertJsonPath('user.has_signature', false);
        Storage::disk('public')->assertMissing($path);
        $this->get("/api/public/users/{$user->id}/signature")->assertNotFound();
    }

    public function test_a_users_signature_appears_on_invoices_they_create(): void
    {
        Storage::fake('public');
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner-sig@example.test', 'password' => 'password']);
        $business = \App\Models\Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Sig Co', 'slug' => 'sig-co-'.uniqid(), 'code' => 'SIG',
            'business_type' => 'service', 'currency' => 'NPR', 'invoice_prefix' => 'SIG', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => \App\Enums\BusinessRole::Owner, 'full_control' => true, 'active' => true]);

        Sanctum::actingAs($owner);
        $this->postJson('/api/profile/signature', ['signature' => UploadedFile::fake()->image('sig.png', 200, 80)])->assertOk();

        $invoice = app(\App\Services\InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Walk-in', 'invoice_date' => today()->toDateString(), 'status' => 'issued', 'discount_amount' => 0,
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 100, 'discount_amount' => 0, 'tax_rate' => 0]],
        ]);

        $response = $this->getJson("/api/businesses/{$business->id}/invoices/{$invoice->id}")->assertOk();
        $this->assertNotNull($response->json('data.creator.signature_url'));
    }
}
