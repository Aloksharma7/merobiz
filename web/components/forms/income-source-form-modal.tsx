"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, IncomeSourceType, PersonalIncomeSource } from "@/lib/types";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState, type FormEvent } from "react";
import { toast } from "sonner";

const blank = { name: "", type: "bank" as IncomeSourceType, notes: "", active: true };

export function IncomeSourceFormModal({ open, onClose, source }: { open: boolean; onClose: () => void; source?: PersonalIncomeSource | null }) {
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      setForm(source ? { name: source.name, type: source.type, notes: source.notes ?? "", active: source.active } : blank);
      setErrors({});
    }
  }, [source, open]);

  const mutation = useMutation({
    mutationFn: async () => {
      const path = source ? `/personal/income-sources/${source.id}` : "/personal/income-sources";
      const response = source
        ? await api.patch<ApiMessage<{ source: PersonalIncomeSource }>>(path, form)
        : await api.post<ApiMessage<{ source: PersonalIncomeSource }>>(path, form);
      return response.data;
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["personal-income-sources"] });
      toast.success(source ? "Income source updated" : "Income source added");
      onClose();
    },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not save income source", { description: apiError(error) }); },
  });

  function update<K extends keyof typeof form>(key: K, value: (typeof form)[K]) { setForm((current) => ({ ...current, [key]: value })); }

  function submit(event: FormEvent) {
    event.preventDefault();
    mutation.mutate();
  }

  return (
    <Modal open={open} onClose={onClose} title={source ? "Edit income source" : "Add income source"} description="A bank account, side income or anything else you earn from outside your businesses." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="income-source-form" loading={mutation.isPending}>Save source</Button></>}>
      <form id="income-source-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
        <FieldShell label="Name" htmlFor="income-source-name" error={errors.name?.[0]} required className="sm:col-span-2"><Input id="income-source-name" value={form.name} onChange={(event) => update("name", event.target.value)} placeholder="e.g. Nabil Bank savings" autoFocus required /></FieldShell>
        <FieldShell label="Type" htmlFor="income-source-type" error={errors.type?.[0]}><Select id="income-source-type" value={form.type} onChange={(event) => update("type", event.target.value as IncomeSourceType)}><option value="bank">Bank</option><option value="salary">Salary</option><option value="investment">Investment</option><option value="other">Other</option></Select></FieldShell>
        {source ? <label className="flex items-center gap-2.5 self-end pb-2.5 text-sm font-semibold"><input type="checkbox" checked={form.active} onChange={(event) => update("active", event.target.checked)} className="h-4 w-4 accent-[var(--brand)]" />Active</label> : null}
        <FieldShell label="Notes" htmlFor="income-source-notes" error={errors.notes?.[0]} className="sm:col-span-2"><Textarea id="income-source-notes" value={form.notes} onChange={(event) => update("notes", event.target.value)} placeholder="Account details, terms, or anything worth remembering" /></FieldShell>
      </form>
    </Modal>
  );
}
