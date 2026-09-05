<?php

namespace App\Support;

use App\Models\Business;
use App\Models\Invoice;
use Illuminate\Http\Request;

trait AuthorizesInvoiceAccess
{
    private function assertVisible(Request $request, Business $business, Invoice $invoice): void
    {
        abort_unless($invoice->business_id === $business->id, 404);
        $membership = $this->membership($request);
        $canViewAll = $membership->allows('sales.view') || $membership->allows('sales.manage');
        $canViewOwn = ($membership->allows('sales.view_own') || $membership->allows('sales.create'))
            && $invoice->created_by === $request->user()->id;
        abort_unless($canViewAll || $canViewOwn, 403);
    }

    private function assertEditable(Request $request, Business $business, Invoice $invoice): void
    {
        abort_unless($invoice->business_id === $business->id, 404);
        $membership = $this->membership($request);
        $canManage = $membership->allows('sales.manage');
        $canEditOwn = $membership->allows('sales.create')
            && $invoice->created_by === $request->user()->id;
        abort_unless($canManage || $canEditOwn, 403);
    }
}
