"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { WriterProjectRow } from "@/lib/types";
import { money, today } from "@/lib/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";

export function WriterPaymentFormModal({
  businessId,
  writerId,
  open,
  onClose,
  currency,
  currentProjects,
}: {
  businessId: string | number;
  writerId: string | number;
  open: boolean;
  onClose: () => void;
  currency: string;
  currentProjects: WriterProjectRow[];
}) {
  const [projectId, setProjectId] = useState("");
  const [amount, setAmount] = useState("");
  const [paidOn, setPaidOn] = useState(today());
  const [notes, setNotes] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      setProjectId(currentProjects.length === 1 ? String(currentProjects[0].id) : "");
      setAmount("");
      setPaidOn(today());
      setNotes("");
      setErrors({});
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const project = currentProjects.find((row) => row.id === Number(projectId));
  const due = project?.writer_due_amount ?? 0;
  const amountExceedsDue = Boolean(project) && Number(amount) > due + 0.001;

  const mutation = useMutation({
    mutationFn: async () => (await api.post(`/businesses/${businessId}/writers/${writerId}/payments`, {
      project_id: Number(projectId),
      paid_on: paidOn,
      amount: Number(amount),
      notes: notes || null,
    })).data,
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["writer", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["projects", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["project", String(businessId)] }),
      ]);
      toast.success("Writer payment recorded", { description: `${money(Number(amount), currency)} for ${project?.client_name}` });
      onClose();
    },
    onError: (error) => {
      setErrors(fieldErrors(error));
      toast.error("Could not record payment", { description: apiError(error) });
      void queryClient.invalidateQueries({ queryKey: ["writer", String(businessId)] });
    },
  });

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Pay writer"
      description="Record money given to this writer for one of their files."
      footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="writer-payment-form" loading={mutation.isPending} disabled={amountExceedsDue || !projectId}>Record payment</Button></>}
    >
      <form id="writer-payment-form" onSubmit={(event) => { event.preventDefault(); setErrors({}); mutation.mutate(); }} className="space-y-4">
        <FieldShell label="File" htmlFor="writer-payment-project" error={errors.project_id?.[0]} required>
          <Select id="writer-payment-project" value={projectId} onChange={(event) => { setProjectId(event.target.value); setAmount(""); }} required>
            <option value="">Select which file this payment is for</option>
            {currentProjects.map((row) => <option key={row.id} value={row.id}>{row.client_name} · {row.course}</option>)}
          </Select>
        </FieldShell>
        {project ? (
          <div className="rounded-2xl bg-[var(--surface-soft)] p-4 text-sm">
            <div className="flex items-center justify-between"><span className="text-[var(--ink-soft)]">Total amount to pay</span><span className="font-bold">{money(project.writer_payment_amount, currency)}</span></div>
            <div className="mt-1.5 flex items-center justify-between"><span className="text-[var(--ink-soft)]">Paid</span><span className="font-bold">− {money(project.writer_paid_amount, currency)}</span></div>
            <div className="my-2 border-t border-[var(--line)]" />
            <div className="flex items-center justify-between"><span className="font-semibold">Due to writer</span><span className="font-black">{money(due, currency)}</span></div>
          </div>
        ) : null}
        <div className="grid gap-4 sm:grid-cols-2">
          <FieldShell label="Amount" htmlFor="writer-payment-amount" error={errors.amount?.[0] ?? (amountExceedsDue ? `Cannot exceed the due amount of ${money(due, currency)}.` : undefined)} required>
            <Input id="writer-payment-amount" type="number" min="0.01" max={project ? due : undefined} step="0.01" placeholder="0.00" value={amount} onChange={(event) => setAmount(event.target.value)} disabled={!project} required />
          </FieldShell>
          <FieldShell label="Date" htmlFor="writer-payment-date" error={errors.paid_on?.[0]} required>
            <Input id="writer-payment-date" type="date" max={today()} value={paidOn} onChange={(event) => setPaidOn(event.target.value)} required />
          </FieldShell>
        </div>
        <FieldShell label="Notes" htmlFor="writer-payment-notes" error={errors.notes?.[0]}>
          <Textarea id="writer-payment-notes" value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Any extra detail worth keeping" />
        </FieldShell>
      </form>
    </Modal>
  );
}
