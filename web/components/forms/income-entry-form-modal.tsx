"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, PersonalIncomeEntry, PersonalIncomeSource } from "@/lib/types";
import { today } from "@/lib/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState, type FormEvent } from "react";
import { toast } from "sonner";

export function IncomeEntryFormModal({ open, onClose, sources }: { open: boolean; onClose: () => void; sources: PersonalIncomeSource[] }) {
  const [sourceId, setSourceId] = useState("");
  const [entryDate, setEntryDate] = useState(today());
  const [amount, setAmount] = useState("");
  const [notes, setNotes] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      setSourceId(sources[0] ? String(sources[0].id) : "");
      setEntryDate(today());
      setAmount("");
      setNotes("");
      setErrors({});
    }
  }, [open, sources]);

  const mutation = useMutation({
    mutationFn: async () => (await api.post<ApiMessage<{ entry: PersonalIncomeEntry }>>("/personal/income-entries", {
      source_id: Number(sourceId), entry_date: entryDate, amount: Number(amount), notes: notes || null,
    })).data,
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["personal-income-entries"] }),
        queryClient.invalidateQueries({ queryKey: ["personal-overview"] }),
      ]);
      toast.success("Income recorded");
      onClose();
    },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not record income", { description: apiError(error) }); },
  });

  function submit(event: FormEvent) {
    event.preventDefault();
    mutation.mutate();
  }

  return (
    <Modal open={open} onClose={onClose} title="Log income" description="Record a payment received from one of your income sources." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="income-entry-form" loading={mutation.isPending}>Record income</Button></>}>
      <form id="income-entry-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
        <FieldShell label="Source" htmlFor="income-entry-source" error={errors.source_id?.[0]} required className="sm:col-span-2"><Select id="income-entry-source" value={sourceId} onChange={(event) => setSourceId(event.target.value)} required>{sources.map((source) => <option value={source.id} key={source.id}>{source.name}</option>)}</Select></FieldShell>
        <FieldShell label="Date" htmlFor="income-entry-date" error={errors.entry_date?.[0]} required><Input id="income-entry-date" type="date" max={today()} value={entryDate} onChange={(event) => setEntryDate(event.target.value)} required /></FieldShell>
        <FieldShell label="Amount" htmlFor="income-entry-amount" error={errors.amount?.[0]} required><Input id="income-entry-amount" type="number" min="0.01" step="0.01" placeholder="0.00" value={amount} onChange={(event) => setAmount(event.target.value)} required /></FieldShell>
        <FieldShell label="Notes" htmlFor="income-entry-notes" error={errors.notes?.[0]} className="sm:col-span-2"><Textarea id="income-entry-notes" value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Any extra detail worth keeping" /></FieldShell>
      </form>
    </Modal>
  );
}
