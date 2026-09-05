<?php

namespace App\Http\Controllers;

use App\Enums\BusinessRole;
use App\Http\Requests\StoreProfitWithdrawalRequest;
use App\Models\Business;
use App\Support\AuthorizesBusinessActions;
use Illuminate\Http\JsonResponse;

class ProfitWithdrawalController extends Controller
{
    use AuthorizesBusinessActions;

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
}
