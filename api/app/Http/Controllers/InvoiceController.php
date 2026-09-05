<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Business;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Support\AuthorizesBusinessActions;
use App\Support\AuthorizesInvoiceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    use AuthorizesBusinessActions;
    use AuthorizesInvoiceAccess;

    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        $membership = $this->membership($request);
        $canViewAll = $membership->allows('sales.view') || $membership->allows('sales.manage');
        $canViewOwn = $membership->allows('sales.view_own') || $membership->allows('sales.create');
        abort_unless($canViewAll || $canViewOwn, 403);
        $onlyMine = ! $canViewAll || $request->boolean('mine');

        $invoices = $business->invoices()
            ->with(['customer:id,name,phone,email,pan_number,address', 'project:id,client_name', 'creator:id,name', 'items.product:id,name', 'payments.recorder:id,name'])
            ->when($onlyMine, fn ($query) => $query->where('created_by', $request->user()->id))
            ->when($request->filled('status') && $request->query('status') !== 'all', fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('start'), fn ($query) => $query->whereDate('invoice_date', '>=', $request->query('start')))
            ->when($request->filled('end'), fn ($query) => $query->whereDate('invoice_date', '<=', $request->query('end')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function ($nested) use ($search): void {
                    $nested->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('invoice_date')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 20));

        return InvoiceResource::collection($invoices);
    }

    public function export(Request $request, Business $business): StreamedResponse
    {
        $this->requirePermission($request, 'sales.manage');

        $invoices = $business->invoices()
            ->with(['customer:id,name', 'creator:id,name'])
            ->when($request->boolean('mine'), fn ($query) => $query->where('created_by', $request->user()->id))
            ->when($request->filled('status') && $request->query('status') !== 'all', fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('start'), fn ($query) => $query->whereDate('invoice_date', '>=', $request->query('start')))
            ->when($request->filled('end'), fn ($query) => $query->whereDate('invoice_date', '<=', $request->query('end')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function ($nested) use ($search): void {
                    $nested->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('invoice_date')
            ->latest('id')
            ->get();

        $filename = 'sales-'.$business->code.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($invoices): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice Number', 'Date', 'Customer', 'Seller', 'Status', 'Subtotal', 'Discount', 'Tax', 'Direct Cost', 'Commission', 'Total', 'Paid', 'Balance']);
            foreach ($invoices as $invoice) {
                fputcsv($handle, [
                    $invoice->invoice_number,
                    $invoice->invoice_date->format('Y-m-d'),
                    $invoice->customer_name ?: ($invoice->customer->name ?? 'Walk-in customer'),
                    $invoice->creator->name ?? '—',
                    $invoice->status->value,
                    $invoice->subtotal,
                    $invoice->discount_amount,
                    $invoice->tax_amount,
                    $invoice->cost_amount,
                    $invoice->commission_amount,
                    $invoice->total_amount,
                    $invoice->paid_amount,
                    $invoice->balance_amount,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function store(StoreInvoiceRequest $request, Business $business): JsonResponse
    {
        $membership = $this->membership($request);
        abort_unless($membership->allows('sales.manage') || $membership->allows('sales.create'), 403);

        $invoice = $this->invoices->create($business, $request->user(), $request->validated());

        return response()->json([
            'message' => $invoice->status->value === 'draft' ? 'Draft saved.' : 'Invoice created.',
            'invoice' => new InvoiceResource($invoice),
        ], 201);
    }

    public function show(Request $request, Business $business, Invoice $invoice): InvoiceResource
    {
        $this->assertVisible($request, $business, $invoice);

        return new InvoiceResource($invoice->load(['customer', 'project', 'creator', 'items.product', 'payments.recorder', 'installments']));
    }

    public function issue(Request $request, Business $business, Invoice $invoice): JsonResponse
    {
        $this->assertEditable($request, $business, $invoice);
        $issued = $this->invoices->issue($business, $invoice, $request->user());

        return response()->json(['message' => 'Invoice issued.', 'invoice' => new InvoiceResource($issued)]);
    }

    public function cancel(Request $request, Business $business, Invoice $invoice): JsonResponse
    {
        $this->assertEditable($request, $business, $invoice);
        $cancelled = $this->invoices->cancel($business, $invoice, $request->user());

        return response()->json(['message' => 'Invoice cancelled.', 'invoice' => new InvoiceResource($cancelled)]);
    }

    public function destroy(Request $request, Business $business, Invoice $invoice): JsonResponse
    {
        // Deleting a sale (as opposed to cancelling it) is an admin-only action,
        // regardless of who created the invoice.
        $this->requirePermission($request, 'sales.manage');
        abort_unless($invoice->business_id === $business->id, 404);
        $this->invoices->delete($business, $invoice, $request->user());

        return response()->json(['message' => 'Sale deleted.']);
    }
}
