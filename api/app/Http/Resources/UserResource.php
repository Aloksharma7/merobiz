<?php

namespace App\Http\Resources;

use App\Services\BusinessListingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'initials' => $this->initials,
            'preferred_currency' => $this->preferred_currency,
            'has_signature' => (bool) $this->signature_path,
            'signature_url' => $this->signature_path
                ? url('/api/public/users/'.$this->id.'/signature?v='.($this->updated_at?->timestamp ?? time()))
                : null,
            'workspace' => $this->resolveWorkspace(),
            // Bundled here so the app's initial load (and every login/register) gets
            // the user AND their businesses in one round trip instead of two.
            'businesses' => BusinessResource::collection(app(BusinessListingService::class)->visibleFor($this->resource)),
        ];
    }
}
