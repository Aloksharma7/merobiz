<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignWritersRequest;
use App\Http\Resources\WriterAssignmentResource;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\WriterAssignmentService;
use App\Support\AuthorizesBusinessActions;
use App\Support\AuthorizesInvoiceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceItemWriterController extends Controller
{
    use AuthorizesBusinessActions;
    use AuthorizesInvoiceAccess;

    public function __construct(private readonly WriterAssignmentService $assignments) {}

    public function index(Request $request, Business $business, Invoice $invoice, InvoiceItem $item): AnonymousResourceCollection
    {
        $this->assertVisible($request, $business, $invoice);
        $this->assertItemBelongs($invoice, $item);

        return WriterAssignmentResource::collection(
            $item->writerAssignments()->with('writer')->orderByDesc('assigned_from')->get()
        );
    }

    public function store(AssignWritersRequest $request, Business $business, Invoice $invoice, InvoiceItem $item): JsonResponse
    {
        $this->assertEditable($request, $business, $invoice);
        $this->requirePermission($request, 'writers.manage');
        abort_unless($business->isInstallment(), 422, 'Writers are only available for installment-category businesses.');
        $this->assertItemBelongs($invoice, $item);

        $data = $request->validated();
        $assignments = $this->assignments->assign(
            $item,
            array_values(array_unique($data['writer_ids'])),
            CarbonImmutable::parse($data['date']),
            $data['notes'] ?? null,
            $request->user(),
            $business,
        );

        return response()->json([
            'message' => 'Writer assignment updated.',
            'data' => WriterAssignmentResource::collection($assignments),
        ], 201);
    }

    private function assertItemBelongs(Invoice $invoice, InvoiceItem $item): void
    {
        abort_unless($item->invoice_id === $invoice->id, 404);
    }
}
