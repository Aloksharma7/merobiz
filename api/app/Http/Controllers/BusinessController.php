<?php

namespace App\Http\Controllers;

use App\Enums\BusinessRole;
use App\Http\Requests\StoreBusinessRequest;
use App\Http\Requests\UpdateBusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Services\AuditService;
use App\Support\AuthorizesBusinessActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BusinessController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $activeMemberships = $request->user()->memberships()->where('active', true);
        $hasPortfolioRole = (clone $activeMemberships)
            ->whereIn('role', [BusinessRole::Owner->value, BusinessRole::Admin->value])
            ->exists();

        $employeeBusinessId = $hasPortfolioRole
            ? null
            : (clone $activeMemberships)
                ->where('role', BusinessRole::Employee->value)
                ->orderBy('id')
                ->value('business_id');

        $businesses = Business::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('active', true))
            ->when($employeeBusinessId, fn ($query) => $query->whereKey($employeeBusinessId))
            ->orderBy('name')
            ->get();

        return BusinessResource::collection($businesses);
    }

    public function store(StoreBusinessRequest $request): JsonResponse
    {
        $activeMemberships = $request->user()->memberships()->where('active', true);
        $hasAnyMembership = (clone $activeMemberships)->exists();
        $hasOwnerMembership = (clone $activeMemberships)->where('role', BusinessRole::Owner->value)->exists();
        abort_unless(! $hasAnyMembership || $hasOwnerMembership, 403, 'Only a portfolio owner can create another business.');

        $business = DB::transaction(function () use ($request): Business {
            $data = $request->validated();
            $code = $this->uniqueCode($data['code'] ?? $data['name']);
            $slug = $this->uniqueSlug($data['name']);

            $business = Business::query()->create([
                'owner_id' => $request->user()->id,
                'name' => $data['name'],
                'slug' => $slug,
                'code' => $code,
                'business_type' => $data['business_type'],
                'currency' => mb_strtoupper($data['currency'] ?? 'NPR'),
                'pan_number' => $data['pan_number'] ?? null,
                'vat_number' => $data['vat_number'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'invoice_prefix' => mb_strtoupper($data['invoice_prefix'] ?? $code),
                'default_tax_rate' => $data['default_tax_rate'] ?? 0,
                'status' => 'active',
                'settings' => [
                    'country' => 'NP',
                    'number_format' => 'en-NP',
                    'branding' => [
                        'tagline' => null,
                        'primary_color' => '#135f48',
                        'nav_color' => '#0b3c31',
                        'accent_color' => '#eaa737',
                        'show_logo_workspace' => true,
                        'show_logo_invoice' => true,
                    ],
                    'invoice' => [
                        'show_seller_address' => true,
                        'show_seller_phone' => true,
                        'show_seller_email' => true,
                        'show_seller_website' => true,
                        'show_seller_pan' => true,
                        'show_customer_address' => true,
                        'show_customer_phone' => true,
                        'show_customer_email' => false,
                        'show_customer_pan' => false,
                        'show_due_date' => true,
                        'show_payment_mode' => true,
                        'show_prepared_by' => false,
                        'show_status' => false,
                        'show_item_discount' => true,
                        'show_item_tax' => true,
                        'show_discount_summary' => true,
                        'show_tax_summary' => true,
                        'show_amount_in_words' => true,
                        'show_bank_details' => false,
                        'show_payment_record' => false,
                        'show_notes' => true,
                        'show_terms' => true,
                        'show_signature' => true,
                    ],
                ],
            ]);

            $business->memberships()->create([
                'user_id' => $request->user()->id,
                'role' => BusinessRole::Owner,
                'title' => 'Owner',
                'commission_rate' => 0,
                'active' => true,
                'joined_at' => now()->toDateString(),
            ]);

            $business->ownerships()->create([
                'user_id' => $request->user()->id,
                'ownership_percent' => $data['ownership_percent'],
                'profit_share_percent' => $data['profit_share_percent'] ?? $data['ownership_percent'],
                'effective_from' => now()->toDateString(),
                'notes' => 'Initial ownership record',
            ]);

            $this->audit->record($request->user(), $business, 'business.created', $business, null, $business->toArray(), $request);

            return $business;
        });

        return response()->json([
            'message' => 'Business created successfully.',
            'business' => new BusinessResource($business),
        ], 201);
    }

    public function show(Request $request, Business $business): BusinessResource
    {
        return new BusinessResource($business);
    }

    public function logo(Business $business): BinaryFileResponse
    {
        $path = data_get($business->settings ?? [], 'branding.logo_path');
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function update(UpdateBusinessRequest $request, Business $business): JsonResponse
    {
        $this->requirePermission($request, 'business.update');
        $before = $business->toArray();
        $data = $request->validated();

        if (isset($data['name']) && $data['name'] !== $business->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $business->id);
        }
        if (isset($data['code'])) {
            $data['code'] = mb_strtoupper($data['code']);
        }
        if (isset($data['invoice_prefix'])) {
            $data['invoice_prefix'] = mb_strtoupper($data['invoice_prefix']);
        }
        if (isset($data['currency'])) {
            $data['currency'] = mb_strtoupper($data['currency']);
            $hasFinancialHistory = $business->invoices()->exists()
                || $business->payments()->exists()
                || $business->expenses()->exists()
                || $business->profitPeriods()->exists();
            if ($data['currency'] !== $business->currency && $hasFinancialHistory) {
                throw ValidationException::withMessages([
                    'currency' => 'Currency cannot be changed after financial transactions exist. Create a separate business workspace instead.',
                ]);
            }
        }

        if (array_key_exists('settings', $data)) {
            $data['settings'] = array_replace_recursive($business->settings ?? [], $data['settings'] ?? []);
        }

        $business->update($data);
        $this->audit->record($request->user(), $business, 'business.updated', $business, $before, $business->fresh()->toArray(), $request);

        return response()->json([
            'message' => 'Business settings updated.',
            'business' => new BusinessResource($business->fresh()),
        ]);
    }

    public function uploadLogo(Request $request, Business $business): JsonResponse
    {
        $this->requirePermission($request, 'business.update');

        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $before = $business->toArray();
        $settings = $business->settings ?? [];
        $previousPath = data_get($settings, 'branding.logo_path');
        $path = $validated['logo']->store("businesses/{$business->id}/branding", 'public');

        data_set($settings, 'branding.logo_path', $path);
        data_set($settings, 'branding.show_logo_workspace', data_get($settings, 'branding.show_logo_workspace', true));
        data_set($settings, 'branding.show_logo_invoice', data_get($settings, 'branding.show_logo_invoice', true));
        $business->update(['settings' => $settings]);

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        $this->audit->record($request->user(), $business, 'business.logo.updated', $business, $before, $business->fresh()->toArray(), $request);

        return response()->json([
            'message' => 'Business logo updated.',
            'business' => new BusinessResource($business->fresh()),
        ]);
    }

    public function destroyLogo(Request $request, Business $business): JsonResponse
    {
        $this->requirePermission($request, 'business.update');

        $before = $business->toArray();
        $settings = $business->settings ?? [];
        $path = data_get($settings, 'branding.logo_path');
        if ($path) Storage::disk('public')->delete($path);
        data_set($settings, 'branding.logo_path', null);
        $business->update(['settings' => $settings]);

        $this->audit->record($request->user(), $business, 'business.logo.removed', $business, $before, $business->fresh()->toArray(), $request);

        return response()->json([
            'message' => 'Business logo removed.',
            'business' => new BusinessResource($business->fresh()),
        ]);
    }

    private function uniqueCode(string $source): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9]+/', '', $source) ?? '';
        $base = mb_strtoupper(Str::substr($normalized, 0, 8));
        $base = $base !== '' ? $base : 'BIZ';
        $candidate = $base;
        $suffix = 1;

        while (Business::query()->where('code', $candidate)->exists()) {
            $candidate = Str::substr($base, 0, 8 - strlen((string) $suffix)).$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'business';
        $candidate = $base;
        $suffix = 2;

        while (Business::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
