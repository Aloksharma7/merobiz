<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRefundRequest;
use App\Models\Business;
use App\Models\Project;
use App\Services\AuditService;
use App\Support\AuthorizesBusinessActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ProjectRefundController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly AuditService $audit) {}

    public function store(StoreProjectRefundRequest $request, Business $business, Project $project): JsonResponse
    {
        $this->requirePermission($request, 'writers.manage');
        abort_unless($business->isInstallment(), 422, 'Projects are only available for installment-category businesses.');
        abort_unless($project->business_id === $business->id, 404);

        $data = $request->validated();

        $refundable = $project->collectedAmount() - $project->refundedAmount();
        if ((float) $data['amount'] > $refundable + 0.01) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'You tried to refund %s, but only %s has been collected on this file (after any earlier refunds).',
                    number_format((float) $data['amount'], 2),
                    number_format(max(0.0, $refundable), 2),
                ),
            ]);
        }

        $refund = $project->refunds()->create([
            'business_id' => $business->id,
            'refunded_by' => $request->user()->id,
            'refunded_on' => $data['refunded_on'],
            'amount' => $data['amount'],
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->record($request->user(), $business, 'project.refunded', $project, null, $refund->toArray(), $request);

        return response()->json([
            'message' => 'Refund recorded.',
            'refunded_amount' => round($project->fresh()->refundedAmount(), 2),
        ], 201);
    }
}
