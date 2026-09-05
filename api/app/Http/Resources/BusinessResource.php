<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $membership = $request->user() ? $this->membershipFor($request->user()) : null;
        $ownership = $request->user() && $membership?->role->value === 'owner'
            ? $this->currentOwnershipFor($request->user())
            : null;

        $settings = $this->settings ?? [];
        $logoPath = data_get($settings, 'branding.logo_path');
        data_set($settings, 'branding.logo_url', $logoPath
            ? url('/api/public/businesses/'.$this->id.'/logo?v='.($this->updated_at?->timestamp ?? time()))
            : null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
            'business_type' => $this->business_type,
            'category' => $this->category->value,
            'product_type' => $this->product_type?->value,
            'is_installment' => $this->isInstallment(),
            'currency' => $this->currency,
            'pan_number' => $this->pan_number,
            'vat_number' => $this->vat_number,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'invoice_prefix' => $this->invoice_prefix,
            'default_tax_rate' => (float) $this->default_tax_rate,
            'status' => $this->status,
            'settings' => $settings,
            'my_role' => $membership?->role->value,
            'full_control' => $membership?->full_control ?? false,
            'is_founder' => $request->user() && $membership && $request->user()->id === $this->owner_id,
            'permissions' => $membership?->effectivePermissions() ?? [],
            'ownership_percent' => $ownership ? (float) $ownership->ownership_percent : 0,
            'profit_share_percent' => $ownership ? (float) $ownership->profit_share_percent : 0,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
