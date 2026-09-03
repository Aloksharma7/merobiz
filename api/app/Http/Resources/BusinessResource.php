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
            'permissions' => $membership?->role->permissions() ?? [],
            'ownership_percent' => $ownership ? (float) $ownership->ownership_percent : 0,
            'profit_share_percent' => $ownership ? (float) $ownership->profit_share_percent : 0,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
