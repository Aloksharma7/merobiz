<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function record(
        ?User $user,
        ?Business $business,
        string $action,
        ?Model $model = null,
        ?array $before = null,
        ?array $after = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'business_id' => $business?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
