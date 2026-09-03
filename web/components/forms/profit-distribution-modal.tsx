"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, PaymentMethod, ProfitAllocation, ProfitDistribution } from "@/lib/types";
import { money, today } from "@/lib/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { toast } from "sonner";

export function ProfitDistributionModal({ businessId, open, onClose, allocations, currency }: { businessId: string | number; open: boolean; onClose: () => void; allocations: ProfitAllocation[]; currency: string }) {
  const available = useMemo(() => allocations.filter((row) => row.remaining_amount > 0), [allocations]);
  const [allocationId, setAllocationId] = useState("");
  const [date, setDate] = useState(today());
  const [amount, setAmount] = useState("");
  const [method, setMethod] = useState<PaymentMethod>("bank_transfer");
  const [reference, setReference] = useState("");
  const [notes, setNotes] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();
  const selected = available.find((row) => row.id === Number(allocationId));
  useEffect(() => { if (open) { const first = available[0]; setAllocationId(first ? String(first.id) : ""); setAmount(first ? String(first.remaining_amount) : ""); setDate(today()); setMethod("bank_transfer"); setReference(""); setNotes(""); setErrors({}); } }, [available, open]);
  const mutation = useMutation({
    mutationFn: async () => (await api.post<ApiMessage<{ distribution: ProfitDistribution }>>(`/businesses/${businessId}/profit-distributions`, { profit_allocation_id: Number(allocationId), distribution_date: date, amount: Number(amount), method, reference: reference || null, notes: notes || null })).data,
    onSuccess: async () => { await Promise.all([queryClient.invalidateQueries({ queryKey: ["profit-periods", String(businessId)] }), queryClient.invalidateQueries({ queryKey: ["profit-distributions", String(businessId)] }), queryClient.invalidateQueries({ queryKey: ["portfolio-dashboard"] })]); toast.success("Profit distribution recorded"); onClose(); },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not record distribution", { description: apiError(error) }); },
  });
  return (
    <Modal open={open} onClose={onClose} title="Record profit distribution" description="This records money actually paid to a partner. It does not change the business profit." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="distribution-form" loading={mutation.isPending} disabled={!available.length}>Record distribution</Button></>}>
      <form id="distribution-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }} className="space-y-4">
        {!available.length ? <div className="rounded-2xl bg-[var(--surface-soft)] p-5 text-sm text-[var(--ink-soft)]">There are no outstanding partner allocations. Close a profitable period first, or all allocations have already been paid.</div> : <>
          <FieldShell label="Partner allocation" htmlFor="allocation" error={errors.profit_allocation_id?.[0]} required><Select id="allocation" value={allocationId} onChange={(event) => { setAllocationId(event.target.value); const row = available.find((item) => item.id === Number(event.target.value)); if (row) setAmount(String(row.remaining_amount)); }}>{available.map((row) => <option value={row.id} key={row.id}>{row.name} — {money(row.remaining_amount, currency)} remaining</option>)}</Select></FieldShell>
          {selected ? <div className="grid grid-cols-2 gap-3 rounded-2xl bg-[var(--surface-soft)] p-4 text-sm"><div><p className="text-xs text-[var(--ink-soft)]">Allocated</p><p className="mt-1 font-black">{money(selected.allocated_amount, currency)}</p></div><div><p className="text-xs text-[var(--ink-soft)]">Already paid</p><p className="mt-1 font-black">{money(selected.distributed_amount, currency)}</p></div></div> : null}
          <div className="grid gap-4 sm:grid-cols-2"><FieldShell label="Distribution date" htmlFor="distribution-date" error={errors.distribution_date?.[0]} required><Input id="distribution-date" type="date" max={today()} value={date} onChange={(event) => setDate(event.target.value)} required /></FieldShell><FieldShell label="Amount paid" htmlFor="distribution-amount" error={errors.amount?.[0]} required><Input id="distribution-amount" type="number" min="0.01" max={selected?.remaining_amount} step="0.01" value={amount} onChange={(event) => setAmount(event.target.value)} required /></FieldShell></div>
          <div className="grid gap-4 sm:grid-cols-2"><FieldShell label="Payment method" htmlFor="distribution-method" error={errors.method?.[0]}><Select id="distribution-method" value={method} onChange={(event) => setMethod(event.target.value as PaymentMethod)}><option value="bank_transfer">Bank transfer</option><option value="qr">QR payment</option><option value="cash">Cash</option><option value="cheque">Cheque</option><option value="wallet">Wallet</option><option value="other">Other</option></Select></FieldShell><FieldShell label="Reference" htmlFor="distribution-reference" error={errors.reference?.[0]}><Input id="distribution-reference" value={reference} onChange={(event) => setReference(event.target.value)} /></FieldShell></div>
          <FieldShell label="Notes" htmlFor="distribution-notes" error={errors.notes?.[0]}><Textarea id="distribution-notes" value={notes} onChange={(event) => setNotes(event.target.value)} /></FieldShell>
        </>}
      </form>
    </Modal>
  );
}
