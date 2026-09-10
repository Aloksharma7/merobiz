<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWriterPaymentRequest;
use App\Models\Business;
use App\Models\Project;
use App\Models\Writer;
use App\Services\AuditService;
use App\Support\AuthorizesBusinessActions;
use App\Support\Decimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WriterPaymentController extends Controller
{
    use AuthorizesBusinessActions;

    public function __construct(private readonly AuditService $audit) {}

    /**
     * Every writer payment across the whole business — shown alongside
     * expenses (see SalaryController::businessIndex() for the same pattern
     * with payroll) so the real cost of running the business is visible in
     * one place instead of split across per-writer/per-project views.
     */
    public function businessIndex(Request $request, Business $business): JsonResponse
    {
        $this->requirePermission($request, 'writers.manage');

        $payments = $business->writerPayments()
            ->with(['writer:id,name', 'project:id,topic', 'recorder:id,name'])
            ->latest('paid_on')
            ->latest('id')
            ->get()
            ->map(fn ($payment) => [
                'id' => $payment->id,
                'paid_on' => $payment->paid_on->toDateString(),
                'amount' => (float) $payment->amount,
                'notes' => $payment->notes,
                'recorded_by' => $payment->recorder?->name,
                'writer_name' => $payment->writer?->name,
                'project_topic' => $payment->project?->topic,
            ]);

        return response()->json(['data' => $payments]);
    }

    public function store(StoreWriterPaymentRequest $request, Business $business, Writer $writer): JsonResponse
    {
        $this->requirePermission($request, 'writers.manage');
        abort_unless($business->isInstallment(), 422, 'Writers are only available for installment-category businesses.');
        abort_unless($writer->business_id === $business->id, 404);

        $data = $request->validated();

        /** @var Project|null $project */
        $project = $business->projects()->whereKey($data['project_id'])->first();
        abort_unless($project, 422, 'The selected file does not belong to this business.');

        $isAssigned = $project->writerAssignments()->where('writer_id', $writer->id)->exists();
        abort_unless($isAssigned, 422, 'This writer has never been assigned to that file.');

        $payment = DB::transaction(function () use ($project, $writer, $data, $request, $business) {
            if (Decimal::of($data['amount'])->isGreaterThan(Decimal::of($project->writerDueAmount())->plus(0.01))) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'You tried to pay %s, but only %s is still due to the writer for this file.',
                        number_format((float) $data['amount'], 2),
                        number_format($project->writerDueAmount(), 2),
                    ),
                ]);
            }

            return $writer->payments()->create([
                'business_id' => $business->id,
                'project_id' => $project->id,
                'recorded_by' => $request->user()->id,
                'paid_on' => $data['paid_on'],
                'amount' => $data['amount'],
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $this->audit->record($request->user(), $business, 'writer.paid', $payment, null, $payment->toArray(), $request);

        return response()->json([
            'message' => 'Payment recorded.',
            'writer_paid_amount' => round($project->fresh()->writerPaidAmount(), 2),
            'writer_due_amount' => round($project->fresh()->writerDueAmount(), 2),
        ], 201);
    }
}
