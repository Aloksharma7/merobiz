<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonalExpenseRequest;
use App\Http\Resources\PersonalExpenseResource;
use App\Models\PersonalExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonalExpenseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $expenses = $request->user()->personalExpenses()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function ($nested) use ($search): void {
                    $nested->where('category', 'like', "%{$search}%")
                        ->orWhere('vendor', 'like', "%{$search}%");
                });
            })
            ->latest('expense_date')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 20));

        return PersonalExpenseResource::collection($expenses);
    }

    public function store(StorePersonalExpenseRequest $request): JsonResponse
    {
        $expense = $request->user()->personalExpenses()->create($request->validated());

        return response()->json([
            'message' => 'Expense recorded.',
            'expense' => new PersonalExpenseResource($expense),
        ], 201);
    }

    public function update(StorePersonalExpenseRequest $request, PersonalExpense $expense): JsonResponse
    {
        $this->assertOwner($request, $expense);
        $expense->update($request->validated());

        return response()->json(['message' => 'Expense updated.', 'expense' => new PersonalExpenseResource($expense->fresh())]);
    }

    public function destroy(Request $request, PersonalExpense $expense): JsonResponse
    {
        $this->assertOwner($request, $expense);
        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    private function assertOwner(Request $request, PersonalExpense $expense): void
    {
        abort_unless($expense->user_id === $request->user()->id, 404);
    }
}
