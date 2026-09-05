"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, PaymentMethod, PersonalExpense } from "@/lib/types";
import { today } from "@/lib/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";

const blank = { category: "", vendor: "", expense_date: today(), amount: "", payment_method: "cash" as PaymentMethod, notes: "" };

export function PersonalExpenseFormModal({ open, onClose, expense }: { open: boolean; onClose: () => void; expense?: PersonalExpense | null }) {
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      setForm(expense ? {
        category: expense.category, vendor: expense.vendor ?? "", expense_date: expense.expense_date,
        amount: String(expense.amount), payment_method: expense.payment_method, notes: expense.notes ?? "",
      } : { ...blank, expense_date: today() });
      setErrors({});
    }
  }, [expense, open]);

  const mutation = useMutation({
    mutationFn: async () => {
      const path = expense ? `/personal/expenses/${expense.id}` : "/personal/expenses";
      const payload = { ...form, vendor: form.vendor || null, amount: Number(form.amount), notes: form.notes || null };
      const response = expense
        ? await api.patch<ApiMessage<{ expense: PersonalExpense }>>(path, payload)
        : await api.post<ApiMessage<{ expense: PersonalExpense }>>(path, payload);
      return response.data;
    },
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["personal-expenses"] }),
        queryClient.invalidateQueries({ queryKey: ["personal-overview"] }),
      ]);
      toast.success(expense ? "Expense updated" : "Expense recorded");
      onClose();
    },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not save expense", { description: apiError(error) }); },
  });

  function update<K extends keyof typeof form>(key: K, value: (typeof form)[K]) { setForm((current) => ({ ...current, [key]: value })); }

  return (
    <Modal open={open} onClose={onClose} title={expense ? "Edit expense" : "Add personal expense"} description="Track your own spending, separate from any business's books." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="personal-expense-form" loading={mutation.isPending}>Save expense</Button></>}>
      <form id="personal-expense-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }} className="grid gap-4 sm:grid-cols-2">
        <FieldShell label="Category" htmlFor="personal-expense-category" error={errors.category?.[0]} required><Input id="personal-expense-category" value={form.category} onChange={(event) => update("category", event.target.value)} autoFocus placeholder="e.g. Groceries" required /></FieldShell>
        <FieldShell label="Vendor" htmlFor="personal-expense-vendor" error={errors.vendor?.[0]} hint="Optional"><Input id="personal-expense-vendor" value={form.vendor} onChange={(event) => update("vendor", event.target.value)} placeholder="e.g. Big Mart" /></FieldShell>
        <FieldShell label="Date" htmlFor="personal-expense-date" error={errors.expense_date?.[0]} required><Input id="personal-expense-date" type="date" max={today()} value={form.expense_date} onChange={(event) => update("expense_date", event.target.value)} required /></FieldShell>
        <FieldShell label="Amount" htmlFor="personal-expense-amount" error={errors.amount?.[0]} required><Input id="personal-expense-amount" type="number" min="0.01" step="0.01" placeholder="0.00" value={form.amount} onChange={(event) => update("amount", event.target.value)} required /></FieldShell>
        <FieldShell label="Payment method" htmlFor="personal-expense-method" error={errors.payment_method?.[0]} required className="sm:col-span-2"><Select id="personal-expense-method" value={form.payment_method} onChange={(event) => update("payment_method", event.target.value as PaymentMethod)}><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="qr">QR payment</option><option value="card">Card</option><option value="wallet">Digital wallet</option><option value="cheque">Cheque</option><option value="other">Other</option></Select></FieldShell>
        <FieldShell label="Notes" htmlFor="personal-expense-notes" error={errors.notes?.[0]} className="sm:col-span-2"><Textarea id="personal-expense-notes" value={form.notes} onChange={(event) => update("notes", event.target.value)} placeholder="Any extra detail worth keeping" /></FieldShell>
      </form>
    </Modal>
  );
}
