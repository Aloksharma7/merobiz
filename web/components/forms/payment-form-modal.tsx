"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, Invoice, Payment, PaymentMethod } from "@/lib/types";
import { money, today } from "@/lib/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";

export function PaymentFormModal({ businessId, invoice, open, onClose, currency }: { businessId: string | number; invoice: Invoice | null; open: boolean; onClose: () => void; currency: string }) {
  const [date, setDate] = useState(today());
  const [amount, setAmount] = useState("");
  const [method, setMethod] = useState<PaymentMethod>("qr");
  const [reference, setReference] = useState("");
  const [notes, setNotes] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  useEffect(() => { if (open && invoice) { setDate(today()); setAmount(String(invoice.balance_amount)); setMethod("qr"); setReference(""); setNotes(""); setErrors({}); } }, [invoice, open]);
  const mutation = useMutation({
    mutationFn: async () => (await api.post<ApiMessage<{ payment: Payment }>>(`/businesses/${businessId}/invoices/${invoice?.id}/payments`, { payment_date: date, amount: Number(amount), method, reference: reference || null, notes: notes || null })).data,
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["invoices", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["invoice", String(businessId), invoice?.id] }),
        queryClient.invalidateQueries({ queryKey: ["business-dashboard", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["portfolio-dashboard"] }),
      ]);
      toast.success("Payment recorded", { description: `${invoice?.invoice_number} balance updated.` });
      onClose();
    },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not record payment", { description: apiError(error) }); },
  });
  if (!invoice) return null;
  return (
    <Modal open={open} onClose={onClose} title="Record payment" description={`${invoice.invoice_number} · ${invoice.customer_name || invoice.customer?.name || "Walk-in customer"}`} footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="payment-form" loading={mutation.isPending}>Record payment</Button></>}>
      <form id="payment-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }} className="space-y-4">
        <div className="rounded-2xl bg-[var(--surface-soft)] p-4"><div className="flex items-center justify-between text-sm"><span className="text-[var(--ink-soft)]">Outstanding balance</span><strong className="text-lg font-black">{money(invoice.balance_amount, currency)}</strong></div></div>
        <div className="grid gap-4 sm:grid-cols-2">
          <FieldShell label="Payment date" htmlFor="payment-date" error={errors.payment_date?.[0]} required><Input id="payment-date" type="date" min={invoice.invoice_date} max={today()} value={date} onChange={(event) => setDate(event.target.value)} required /></FieldShell>
          <FieldShell label="Amount received" htmlFor="payment-amount" error={errors.amount?.[0]} required><Input id="payment-amount" type="number" min="0.01" max={invoice.balance_amount} step="0.01" value={amount} onChange={(event) => setAmount(event.target.value)} required /></FieldShell>
          <FieldShell label="Method" htmlFor="payment-method" error={errors.method?.[0]} required><Select id="payment-method" value={method} onChange={(event) => setMethod(event.target.value as PaymentMethod)}><option value="qr">QR payment</option><option value="bank_transfer">Bank transfer</option><option value="cash">Cash</option><option value="wallet">Digital wallet</option><option value="card">Card</option><option value="cheque">Cheque</option><option value="other">Other</option></Select></FieldShell>
          <FieldShell label="Reference" htmlFor="payment-reference" error={errors.reference?.[0]}><Input id="payment-reference" value={reference} onChange={(event) => setReference(event.target.value)} /></FieldShell>
        </div>
        <FieldShell label="Notes" htmlFor="payment-notes" error={errors.notes?.[0]}><Textarea id="payment-notes" value={notes} onChange={(event) => setNotes(event.target.value)} /></FieldShell>
      </form>
    </Modal>
  );
}
