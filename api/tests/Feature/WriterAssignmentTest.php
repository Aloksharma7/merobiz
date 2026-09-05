<?php

namespace Tests\Feature;

use App\Enums\BusinessRole;
use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WriterAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_writer_service_rejects_a_second_concurrent_writer_while_multi_writer_service_accepts_one(): void
    {
        $owner = User::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password']);
        $business = Business::query()->create([
            'owner_id' => $owner->id, 'name' => 'Enlighten Research', 'slug' => 'enlighten', 'code' => 'ERC',
            'business_type' => 'service', 'category' => 'installment', 'currency' => 'NPR', 'invoice_prefix' => 'ERC', 'default_tax_rate' => 0, 'status' => 'active',
        ]);
        $business->memberships()->create(['user_id' => $owner->id, 'role' => BusinessRole::Owner, 'active' => true]);

        $soloService = $business->products()->create([
            'name' => 'Thesis chapter', 'type' => 'service', 'unit' => 'project',
            'sale_price' => 5000, 'cost_price' => 0, 'tax_rate' => 0, 'active' => true, 'allows_multiple_writers' => false,
        ]);
        $teamService = $business->products()->create([
            'name' => 'Full dissertation', 'type' => 'service', 'unit' => 'project',
            'sale_price' => 20000, 'cost_price' => 0, 'tax_rate' => 0, 'active' => true, 'allows_multiple_writers' => true,
        ]);

        $writerA = $business->writers()->create(['name' => 'Writer A', 'active' => true]);
        $writerB = $business->writers()->create(['name' => 'Writer B', 'active' => true]);

        $project = $business->projects()->create([
            'created_by' => $owner->id, 'client_name' => 'Client', 'topic' => 'x', 'course' => 'x', 'work' => 'x', 'deal_amount' => 25000,
        ]);

        $invoice = app(InvoiceService::class)->create($business, $owner, [
            'customer_name' => 'Client',
            'project_id' => $project->id,
            'invoice_date' => today()->toDateString(),
            'status' => InvoiceStatus::Issued->value,
            'discount_amount' => 0,
            'items' => [
                ['product_id' => $soloService->id, 'description' => 'Thesis chapter', 'quantity' => 1, 'unit_price' => 5000, 'discount_amount' => 0, 'tax_rate' => 0],
                ['product_id' => $teamService->id, 'description' => 'Full dissertation', 'quantity' => 1, 'unit_price' => 20000, 'discount_amount' => 0, 'tax_rate' => 0],
            ],
        ]);
        $soloItem = $invoice->items->firstWhere('product_id', $soloService->id);
        $teamItem = $invoice->items->firstWhere('product_id', $teamService->id);

        Sanctum::actingAs($owner);

        // Single-writer service rejects two writers at once.
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice->id}/items/{$soloItem->id}/writers", [
            'writer_ids' => [$writerA->id, $writerB->id],
            'date' => today()->toDateString(),
        ])->assertUnprocessable();

        // Multi-writer service accepts both.
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice->id}/items/{$teamItem->id}/writers", [
            'writer_ids' => [$writerA->id, $writerB->id],
            'date' => today()->toDateString(),
        ])->assertCreated()->assertJsonCount(2, 'data');

        // Assign writer A to the single-writer service, then reassign to writer B: history shows the handover.
        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice->id}/items/{$soloItem->id}/writers", [
            'writer_ids' => [$writerA->id],
            'date' => today()->subDays(5)->toDateString(),
        ])->assertCreated();

        $this->postJson("/api/businesses/{$business->id}/invoices/{$invoice->id}/items/{$soloItem->id}/writers", [
            'writer_ids' => [$writerB->id],
            'date' => today()->toDateString(),
        ])->assertCreated();

        $history = $this->getJson("/api/businesses/{$business->id}/invoices/{$invoice->id}/items/{$soloItem->id}/writers")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $history);
        $current = collect($history)->firstWhere('current', true);
        $past = collect($history)->firstWhere('current', false);
        $this->assertSame('Writer B', $current['writer']['name']);
        $this->assertSame('Writer A', $past['writer']['name']);
        $this->assertNotNull($past['assigned_to']);
    }
}
