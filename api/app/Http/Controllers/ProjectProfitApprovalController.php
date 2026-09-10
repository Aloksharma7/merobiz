<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectProfitApprovalRequest;
use App\Models\Business;
use App\Models\Project;
use App\Models\ProjectProfitApproval;
use App\Services\AuditService;
use App\Support\AuthorizesBusinessActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProjectProfitApprovalController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly AuditService $audit) {}

    public function store(StoreProjectProfitApprovalRequest $request, Business $business, Project $project): JsonResponse
    {
        $membership = $this->requirePermission($request, 'writers.manage');
        abort_unless($business->isInstallment(), 422, 'Projects are only available for installment-category businesses.');
        abort_unless($project->business_id === $business->id, 404);
        abort_unless($membership->full_control, 403, 'Only a full-control owner can approve project profit.');

        $data = $request->validated();

        $approval = $project->profitApprovals()->create([
            'business_id' => $business->id,
            'approved_by' => $request->user()->id,
            'approved_on' => $data['approved_on'],
            'amount' => $data['amount'],
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->record($request->user(), $business, 'project.profit_approved', $project, null, $approval->toArray(), $request);

        return response()->json([
            'message' => 'Profit approved and added to the business.',
            'approved_profit_total' => round($project->fresh()->approvedProfitTotal(), 2),
        ], 201);
    }

    /**
     * Undoes an accidentally recorded profit approval — same full-control bar
     * as approving it in the first place, since this directly changes
     * recognized business profit.
     */
    public function destroy(Request $request, Business $business, Project $project, ProjectProfitApproval $approval): JsonResponse
    {
        $membership = $this->requirePermission($request, 'writers.manage');
        abort_unless($business->isInstallment(), 422, 'Projects are only available for installment-category businesses.');
        abort_unless($project->business_id === $business->id, 404);
        abort_unless($approval->project_id === $project->id, 404);
        abort_unless($membership->full_control, 403, 'Only a full-control owner can undo a profit approval.');

        if ($business->isDateClosed($approval->approved_on)) {
            throw ValidationException::withMessages([
                'approval' => 'This approval belongs to a closed profit period.',
            ]);
        }

        $before = $approval->toArray();
        $approval->delete();
        $this->audit->record($request->user(), $business, 'project.profit_approval_deleted', $approval, $before, null, $request);

        return response()->json(['message' => 'Profit approval deleted.']);
    }
}
