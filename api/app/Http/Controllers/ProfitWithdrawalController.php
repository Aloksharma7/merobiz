<?php

namespace App\Http\Controllers;

use App\Enums\BusinessRole;
use App\Http\Requests\StoreProfitWithdrawalRequest;
use App\Models\Business;
use App\Models\ProfitWithdrawal;
use App\Services\AuditService;
use App\Support\AuthorizesBusinessActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfitWithdrawalController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request, Business $business): JsonResponse
    {
        $this->requirePermission($request, 'dashboard.financial');

        $withdrawals = $business->profitWithdrawals()
            ->with('user:id,name')
            ->latest('withdrawn_on')
            ->latest('id')
            ->get()
            ->map(fn ($withdrawal) => [
                'id' => $withdrawal->id,
                'user_id' => $withdrawal->user_id,
                'withdrawn_on' => $withdrawal->withdrawn_on->toDateString(),
                'amount' => (float) $withdrawal->amount,
                'notes' => $withdrawal->notes,
                'user_name' => $withdrawal->user?->name,
            ]);

        return response()->json(['data' => $withdrawals]);
    }

    public function store(StoreProfitWithdrawalRequest $request, Business $business): JsonResponse
    {
        $membership = $this->membership($request);
        abort_unless($membership->role === BusinessRole::Owner, 403, 'Only an owner can log their own profit withdrawals.');

        $data = $request->validated();
        $withdrawal = $business->profitWithdrawals()->create([
            'user_id' => $request->user()->id,
            'withdrawn_on' => $data['withdrawn_on'],
            'amount' => $data['amount'],
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Profit withdrawal recorded.',
            'withdrawal' => [
                'id' => $withdrawal->id,
                'business_id' => $business->id,
                'business_name' => $business->name,
                'withdrawn_on' => $withdrawal->withdrawn_on->toDateString(),
                'amount' => (float) $withdrawal->amount,
                'notes' => $withdrawal->notes,
            ],
        ], 201);
    }

    /**
     * Undoes an accidentally logged withdrawal — same reasoning as
     * SalaryService::deletePayment(). Restricted to the owner who logged it,
     * or a full-control owner fixing someone else's mistake (mirrors the
     * ownership-level bar used for other owners' actions in TeamController).
     */
    public function destroy(Request $request, Business $business, ProfitWithdrawal $withdrawal): JsonResponse
    {
        $membership = $this->requirePermission($request, 'dashboard.financial');
        abort_unless($withdrawal->business_id === $business->id, 404);
        abort_unless($withdrawal->user_id === $request->user()->id || $membership->full_control, 403, 'Only the owner who logged this withdrawal, or a full-control owner, can undo it.');

        if ($business->isDateClosed($withdrawal->withdrawn_on)) {
            throw ValidationException::withMessages([
                'withdrawal' => 'This withdrawal belongs to a closed profit period.',
            ]);
        }

        $before = $withdrawal->toArray();
        $withdrawal->delete();
        $this->audit->record($request->user(), $business, 'profit_withdrawal.deleted', $withdrawal, $before, null, $request);

        return response()->json(['message' => 'Withdrawal deleted.']);
    }
}
