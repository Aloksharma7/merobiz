"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, Expense, PaymentMethod } from "@/lib/types";
import { today } from "@/lib/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";

const blank = { category: "", vendor: "", expense_date: today(), amount: "", tax_amount: "0", payment_method: "bank_transfer" as PaymentMethod, reference: "", notes: "", already_in_sale_price: false };

export function ExpenseFormModal({ businessId, open, onClose }: { businessId: string | number; open: boolean; onClose: () => void }) {
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  useEffect(() => { if (open) { setForm({ ...blank, expense_date: today() }); setErrors({}); } }, [open]);

  const mutation = useMutation({
    mutationFn: async () => {
      const { already_in_sale_price, ...rest } = form;
      return (await api.post<ApiMessage<{ expense: Expense }>>(`/businesses/${businessId}/expenses`, {
        ...rest,
        vendor: form.vendor || null,
        amount: Number(form.amount),
        tax_amount: Number(form.tax_amount || 0),
        reference: form.reference || null,
        notes: form.notes || null,
        affects_profit: !already_in_sale_price,
      })).data;
    },
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["expenses", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["business-dashboard", String(businessId)] }),
      ]);
      toast.success("Expense recorded");
      onClose();
    },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not record expense", { description: apiError(error) }); },
  });

  function update<K extends keyof typeof form>(key: K, value: (typeof form)[K]) { setForm((current) => ({ ...current, [key]: value })); }

  return (
    <Modal open={open} onClose={onClose} title="Add expense" description="Record what was spent so profit reflects the real business result." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="expense-form" loading={mutation.isPending}>Save expense</Button></>} size="lg">
      <form id="expense-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }} className="grid gap-4 sm:grid-cols-2">
        <FieldShell label="Category" htmlFor="expense-category" error={errors.category?.[0]} required><Input id="expense-category" value={form.category} onChange={(event) => update("category", event.target.value)} autoFocus placeholder="e.g. Cloud services" required /></FieldShell>
        <FieldShell label="Vendor" htmlFor="expense-vendor" error={errors.vendor?.[0]} hint="Optional"><Input id="expense-vendor" value={form.vendor} onChange={(event) => update("vendor", event.target.value)} placeholder="e.g. Amazon Web Services" /></FieldShell>
        <FieldShell label="Expense date" htmlFor="expense-date" error={errors.expense_date?.[0]} required><Input id="expense-date" type="date" max={today()} value={form.expense_date} onChange={(event) => update("expense_date", event.target.value)} required /></FieldShell>
        <FieldShell label="Amount" htmlFor="expense-amount" error={errors.amount?.[0]} required><Input id="expense-amount" type="number" min="0.01" step="0.01" placeholder="0.00" value={form.amount} onChange={(event) => update("amount", event.target.value)} required /></FieldShell>
        <FieldShell label="Tax included" htmlFor="expense-tax" error={errors.tax_amount?.[0]} hint="Optional"><Input id="expense-tax" type="number" min="0" step="0.01" placeholder="0.00" value={form.tax_amount} onChange={(event) => update("tax_amount", event.target.value)} /></FieldShell>
        <FieldShell label="Payment method" htmlFor="expense-method" error={errors.payment_method?.[0]} required><Select id="expense-method" value={form.payment_method} onChange={(event) => update("payment_method", event.target.value as PaymentMethod)}><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="qr">QR payment</option><option value="card">Card</option><option value="wallet">Digital wallet</option><option value="cheque">Cheque</option><option value="other">Other</option></Select></FieldShell>
        <FieldShell label="Reference" htmlFor="expense-reference" error={errors.reference?.[0]} hint="Optional"><Input id="expense-reference" value={form.reference} onChange={(event) => update("reference", event.target.value)} placeholder="Invoice or receipt number" /></FieldShell>
        <FieldShell label="Notes" htmlFor="expense-notes" error={errors.notes?.[0]} className="sm:col-span-2"><Textarea id="expense-notes" value={form.notes} onChange={(event) => update("notes", event.target.value)} placeholder="Any extra detail worth keeping" /></FieldShell>
        <label className="flex items-start gap-2.5 rounded-2xl border border-[var(--line)] p-4 text-sm font-semibold sm:col-span-2">
          <input type="checkbox" checked={form.already_in_sale_price} onChange={(event) => update("already_in_sale_price", event.target.checked)} className="mt-0.5 h-4 w-4 accent-[var(--brand)]" />
          <span>
            <span className="block">This is the cost of buying the goods/service for a sale</span>
            <span className="mt-0.5 block text-xs font-normal leading-5 text-[var(--ink-soft)]">Check this when you're paying for something you already sold — its cost was already subtracted from profit at the time of that sale. This will still reduce your available balance (real cash out), just not your profit a second time. Only up to the real cost recognized from your sales is exempt this way — if you check more than that, the extra still counts against profit, since it isn't actually already priced into anything.</span>
          </span>
        </label>
      </form>
    </Modal>
  );
}
