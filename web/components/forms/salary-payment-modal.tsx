"use client";

import { PayrollHistoryList } from "@/components/dashboard/payroll-history-list";
import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, Member, PaymentMethod, SalaryEntryType, SalaryPaymentRecord, SalarySummary } from "@/lib/types";
import { money, today } from "@/lib/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";

export function SalaryPaymentModal({ businessId, member, open, onClose, currency }: { businessId: string | number; member: Member | null; open: boolean; onClose: () => void; currency: string }) {
  const [date, setDate] = useState(today());
  const [amount, setAmount] = useState("");
  const [entryType, setEntryType] = useState<SalaryEntryType>("payment");
  const [method, setMethod] = useState<PaymentMethod>("bank_transfer");
  const [reference, setReference] = useState("");
  const [notes, setNotes] = useState("");
  const [settleAmount, setSettleAmount] = useState("");
  const [settleNotes, setSettleNotes] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [settleErrors, setSettleErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open && member) {
      setDate(today());
      setAmount(member.salary_pending > 0 ? String(member.salary_pending) : "");
      setEntryType("payment");
      setMethod("bank_transfer");
      setReference("");
      setNotes("");
      setSettleAmount("");
      setSettleNotes("");
      setErrors({});
      setSettleErrors({});
    }
  }, [member, open]);

  const historyQuery = useQuery({
    queryKey: ["salary-history", String(businessId), member?.id],
    queryFn: async () => (await api.get<{ summary: SalarySummary; payments: SalaryPaymentRecord[] }>(`/businesses/${businessId}/team/${member?.id}/salary`)).data,
    enabled: open && Boolean(member),
  });
  const summary = historyQuery.data?.summary;

  async function invalidate() {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ["team", String(businessId)] }),
      queryClient.invalidateQueries({ queryKey: ["salary-history", String(businessId), member?.id] }),
    ]);
  }

  const mutation = useMutation({
    mutationFn: async () => (await api.post<ApiMessage<{ summary: SalarySummary }>>(`/businesses/${businessId}/team/${member?.id}/salary/payments`, { payment_date: date, amount: Number(amount), entry_type: entryType, method, reference: reference || null, notes: notes || null })).data,
    onSuccess: async () => {
      await invalidate();
      toast.success(entryType === "loan" ? "Loan recorded" : entryType === "advance" ? "Advance recorded" : "Payment recorded");
      setAmount("");
      setReference("");
      setNotes("");
    },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not record payment", { description: apiError(error) }); },
  });

  const settleMutation = useMutation({
    mutationFn: async () => (await api.post<ApiMessage<{ summary: SalarySummary }>>(`/businesses/${businessId}/team/${member?.id}/salary/write-off`, { amount: Number(settleAmount), notes: settleNotes || null })).data,
    onSuccess: async () => {
      await invalidate();
      toast.success("Loan settled");
      setSettleAmount("");
      setSettleNotes("");
    },
    onError: (error) => { setSettleErrors(fieldErrors(error)); toast.error("Could not settle loan", { description: apiError(error) }); },
  });

  if (!member) return null;

  const pending = summary?.pending ?? member.salary_pending;
  const outstandingLoan = summary?.outstanding_loan ?? member.outstanding_loan;

  return (
    <Modal open={open} onClose={onClose} title={`Payroll · ${member.name}`} description={member.pay_type === "fixed_salary" ? `Rate ${money(member.salary_amount, currency)}/month` : "Commission earned from their sales"} size="lg" footer={<><Button variant="ghost" onClick={onClose}>Close</Button><Button type="submit" form="salary-payment-form" loading={mutation.isPending}>Record</Button></>}>
      <div className="space-y-6">
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="rounded-2xl bg-[var(--surface-soft)] p-4">
            <p className="text-xs font-bold uppercase tracking-[0.1em] text-[var(--ink-soft)]">{pending < 0 ? "Overpaid" : "Owed"}</p>
            <p className={`mt-1 text-xl font-black ${pending < 0 ? "text-[var(--danger)]" : ""}`}>{money(Math.abs(pending), currency)}</p>
            {pending < 0 ? <p className="mt-1 text-[11px] leading-4 text-[var(--ink-soft)]">Will be deducted from what they earn next.</p> : null}
          </div>
          <div className="rounded-2xl bg-[var(--surface-soft)] p-4">
            <p className="text-xs font-bold uppercase tracking-[0.1em] text-[var(--ink-soft)]">Outstanding loan</p>
            <p className="mt-1 text-xl font-black">{money(outstandingLoan, currency)}</p>
            {outstandingLoan > 0 ? <p className="mt-1 text-[11px] leading-4 text-[var(--ink-soft)]">Given as an advance that doesn't count against pay.</p> : null}
          </div>
        </div>

        <form id="salary-payment-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }} className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <FieldShell label="Entry type" htmlFor="salary-entry-type" hint="What kind of payment is this?">
              <Select id="salary-entry-type" value={entryType} onChange={(event) => setEntryType(event.target.value as SalaryEntryType)}>
                <option value="payment">Regular payment</option>
                <option value="advance">Advance (counts against pay)</option>
                <option value="loan">Loan (doesn't count against pay)</option>
              </Select>
            </FieldShell>
            <FieldShell label="Payment date" htmlFor="salary-payment-date" error={errors.payment_date?.[0]} required><Input id="salary-payment-date" type="date" max={today()} value={date} onChange={(event) => setDate(event.target.value)} required /></FieldShell>
            <FieldShell label="Amount" htmlFor="salary-payment-amount" error={errors.amount?.[0]} required><Input id="salary-payment-amount" type="number" min="0.01" step="0.01" placeholder="0.00" value={amount} onChange={(event) => setAmount(event.target.value)} required /></FieldShell>
            <FieldShell label="Method" htmlFor="salary-payment-method" error={errors.method?.[0]} required><Select id="salary-payment-method" value={method} onChange={(event) => setMethod(event.target.value as PaymentMethod)}><option value="bank_transfer">Bank transfer</option><option value="cash">Cash</option><option value="qr">QR payment</option><option value="wallet">Digital wallet</option><option value="card">Card</option><option value="cheque">Cheque</option><option value="other">Other</option></Select></FieldShell>
            <FieldShell label="Reference" htmlFor="salary-payment-reference" error={errors.reference?.[0]}><Input id="salary-payment-reference" value={reference} onChange={(event) => setReference(event.target.value)} placeholder="Transaction reference" /></FieldShell>
          </div>
          <FieldShell label="Notes" htmlFor="salary-payment-notes" error={errors.notes?.[0]}><Textarea id="salary-payment-notes" value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Any extra detail worth keeping" /></FieldShell>
        </form>

        {outstandingLoan > 0 ? (
          <div className="rounded-2xl border border-[var(--line)] p-4">
            <p className="text-sm font-bold">Settle loan</p>
            <p className="mt-1 text-xs leading-5 text-[var(--ink-soft)]">Record what they've paid back, or forgive part of it — either way this reduces the outstanding balance without counting as a new payment.</p>
            <form onSubmit={(event) => { event.preventDefault(); settleMutation.mutate(); }} className="mt-3 grid gap-3 sm:grid-cols-[160px_minmax(0,1fr)_auto] sm:items-end">
              <FieldShell label="Amount" htmlFor="settle-amount" error={settleErrors.amount?.[0]}><Input id="settle-amount" type="number" min="0.01" max={outstandingLoan} step="0.01" placeholder="0.00" value={settleAmount} onChange={(event) => setSettleAmount(event.target.value)} /></FieldShell>
              <FieldShell label="Notes" htmlFor="settle-notes"><Input id="settle-notes" value={settleNotes} onChange={(event) => setSettleNotes(event.target.value)} placeholder="e.g. Repaid in cash, or forgiven" /></FieldShell>
              <Button type="submit" variant="secondary" size="sm" disabled={!settleAmount} loading={settleMutation.isPending}>Settle</Button>
            </form>
          </div>
        ) : null}

        <div>
          <p className="mb-2 text-sm font-bold">History</p>
          {historyQuery.isLoading ? (
            <p className="text-xs text-[var(--ink-soft)]">Loading…</p>
          ) : (
            <div className="max-h-64 overflow-y-auto scrollbar-thin">
              <PayrollHistoryList payments={historyQuery.data?.payments ?? []} currency={currency} />
            </div>
          )}
        </div>
      </div>
    </Modal>
  );
}
