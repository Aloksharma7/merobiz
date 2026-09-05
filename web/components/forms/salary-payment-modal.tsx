"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, Member, PaymentMethod, SalarySummary } from "@/lib/types";
import { money, today } from "@/lib/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";

export function SalaryPaymentModal({ businessId, member, open, onClose, currency }: { businessId: string | number; member: Member | null; open: boolean; onClose: () => void; currency: string }) {
  const [date, setDate] = useState(today());
  const [amount, setAmount] = useState("");
  const [method, setMethod] = useState<PaymentMethod>("bank_transfer");
  const [reference, setReference] = useState("");
  const [notes, setNotes] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  useEffect(() => { if (open && member) { setDate(today()); setAmount(member.salary_pending > 0 ? String(member.salary_pending) : ""); setMethod("bank_transfer"); setReference(""); setNotes(""); setErrors({}); } }, [member, open]);

  const mutation = useMutation({
    mutationFn: async () => (await api.post<ApiMessage<{ summary: SalarySummary }>>(`/businesses/${businessId}/team/${member?.id}/salary/payments`, { payment_date: date, amount: Number(amount), method, reference: reference || null, notes: notes || null })).data,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["team", String(businessId)] });
      toast.success("Salary payment recorded");
      onClose();
    },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not record salary payment", { description: apiError(error) }); },
  });

  if (!member) return null;

  return (
    <Modal open={open} onClose={onClose} title={`Record ${member.pay_type === "fixed_salary" ? "salary" : "commission"} payment · ${member.name}`} description={member.pay_type === "fixed_salary" ? `Rate ${money(member.salary_amount, currency)}/month` : "Payout against commission earned from their sales"} footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="salary-payment-form" loading={mutation.isPending}>Record payment</Button></>}>
      <form id="salary-payment-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }} className="space-y-4">
        <div className="rounded-2xl bg-[var(--surface-soft)] p-4"><div className="flex items-center justify-between text-sm"><span className="text-[var(--ink-soft)]">Estimated owed</span><strong className="text-lg font-black">{money(member.salary_pending, currency)}</strong></div></div>
        <div className="grid gap-4 sm:grid-cols-2">
          <FieldShell label="Payment date" htmlFor="salary-payment-date" error={errors.payment_date?.[0]} required><Input id="salary-payment-date" type="date" max={today()} value={date} onChange={(event) => setDate(event.target.value)} required /></FieldShell>
          <FieldShell label="Amount" htmlFor="salary-payment-amount" error={errors.amount?.[0]} required><Input id="salary-payment-amount" type="number" min="0.01" step="0.01" placeholder="0.00" value={amount} onChange={(event) => setAmount(event.target.value)} required /></FieldShell>
          <FieldShell label="Method" htmlFor="salary-payment-method" error={errors.method?.[0]} required><Select id="salary-payment-method" value={method} onChange={(event) => setMethod(event.target.value as PaymentMethod)}><option value="bank_transfer">Bank transfer</option><option value="cash">Cash</option><option value="qr">QR payment</option><option value="wallet">Digital wallet</option><option value="card">Card</option><option value="cheque">Cheque</option><option value="other">Other</option></Select></FieldShell>
          <FieldShell label="Reference" htmlFor="salary-payment-reference" error={errors.reference?.[0]}><Input id="salary-payment-reference" value={reference} onChange={(event) => setReference(event.target.value)} placeholder="Transaction reference" /></FieldShell>
        </div>
        <FieldShell label="Notes" htmlFor="salary-payment-notes" error={errors.notes?.[0]}><Textarea id="salary-payment-notes" value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Any extra detail worth keeping" /></FieldShell>
      </form>
    </Modal>
  );
}
