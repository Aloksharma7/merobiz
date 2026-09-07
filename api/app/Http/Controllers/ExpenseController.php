<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseStatus;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseStatusRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Business;
use App\Models\Expense;
use App\Services\AuditService;
use App\Support\AuthorizesBusinessActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        $membership = $this->membership($request);
        $canManage = $membership->allows('expenses.manage');
        abort_unless($canManage || $membership->allows('expenses.create'), 403);

        $expenses = $business->expenses()
            ->with(['submitter:id,name', 'approver:id,name'])
            ->when(! $canManage, fn ($query) => $query->where('submitted_by', $request->user()->id))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function ($nested) use ($search): void {
                    $nested->where('category', 'like', "%{$search}%")
                        ->orWhere('vendor', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status') && $request->query('status') !== 'all', fn ($query) => $query->where('status', $request->query('status')))
            ->latest('expense_date')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 20));

        return ExpenseResource::collection($expenses);
    }

    public function export(Request $request, Business $business): StreamedResponse
    {
        $this->requirePermission($request, 'expenses.manage');

        $expenses = $business->expenses()
            ->with(['submitter:id,name', 'approver:id,name'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function ($nested) use ($search): void {
                    $nested->where('category', 'like', "%{$search}%")
                        ->orWhere('vendor', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status') && $request->query('status') !== 'all', fn ($query) => $query->where('status', $request->query('status')))
            ->latest('expense_date')
            ->latest('id')
            ->get();

        $filename = 'expenses-'.$business->code.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($expenses): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Category', 'Vendor', 'Method', 'Status', 'Amount', 'Tax', 'Submitted By', 'Approved By', 'Reference', 'Notes']);
            foreach ($expenses as $expense) {
                fputcsv($handle, [
                    $expense->expense_date->format('Y-m-d'),
                    $expense->category,
                    $expense->vendor ?? '',
                    $expense->payment_method->value,
                    $expense->status->value,
                    $expense->amount,
                    $expense->tax_amount,
                    $expense->submitter->name ?? '—',
                    $expense->approver->name ?? '',
                    $expense->reference ?? '',
                    $expense->notes ?? '',
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function store(StoreExpenseRequest $request, Business $business): JsonResponse
    {
        $membership = $this->membership($request);
        abort_unless($membership->allows('expenses.manage') || $membership->allows('expenses.create'), 403);
        $data = $request->validated();

        if ($business->isDateClosed($data['expense_date'])) {
            throw ValidationException::withMessages(['expense_date' => 'This date belongs to a closed profit period.']);
        }

        $expense = $business->expenses()->create([
            ...$data,
            'submitted_by' => $request->user()->id,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'status' => ExpenseStatus::Approved,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'affects_profit' => $data['affects_profit'] ?? true,
        ]);

        $this->audit->record($request->user(), $business, 'expense.created', $expense, null, $expense->toArray(), $request);

        return response()->json([
            'message' => 'Expense recorded.',
            'expense' => new ExpenseResource($expense->fresh(['submitter', 'approver'])),
        ], 201);
    }

    public function updateStatus(UpdateExpenseStatusRequest $request, Business $business, Expense $expense): JsonResponse
    {
        $this->requirePermission($request, 'expenses.approve');
        $this->assertBusiness($business, $expense);

        if ($business->isDateClosed($expense->expense_date)) {
            throw ValidationException::withMessages(['status' => 'This expense belongs to a closed profit period.']);
        }

        $before = $expense->toArray();
        $status = ExpenseStatus::from($request->validated('status'));
        $expense->update([
            'status' => $status,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $this->audit->record($request->user(), $business, 'expense.'.$status->value, $expense, $before, $expense->fresh()->toArray(), $request);

        return response()->json([
            'message' => $status === ExpenseStatus::Approved ? 'Expense approved.' : 'Expense rejected.',
            'expense' => new ExpenseResource($expense->fresh(['submitter', 'approver'])),
        ]);
    }

    public function destroy(Request $request, Business $business, Expense $expense): JsonResponse
    {
        $membership = $this->membership($request);
        $canManage = $membership->allows('expenses.manage');
        // Expenses are approved immediately now, so there's no "still pending" window
        // to gate this on — instead, the person who submitted it can undo their own
        // mistake on the same day, same as fixing a typo right after making it.
        $canDeleteOwnToday = $membership->allows('expenses.create')
            && $expense->submitted_by === $request->user()->id
            && $expense->created_at->isToday();
        abort_unless($canManage || $canDeleteOwnToday, 403);
        $this->assertBusiness($business, $expense);

        if ($business->isDateClosed($expense->expense_date)) {
            throw ValidationException::withMessages(['expense' => 'This expense belongs to a closed profit period.']);
        }

        $before = $expense->toArray();
        $expense->delete();
        $this->audit->record($request->user(), $business, 'expense.deleted', $expense, $before, null, $request);

        return response()->json(['message' => 'Expense deleted.']);
    }

    private function assertBusiness(Business $business, Expense $expense): void
    {
        abort_unless($expense->business_id === $business->id, 404);
    }
}
