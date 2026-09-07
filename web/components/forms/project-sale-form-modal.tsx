"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, Invoice, Paginated, PaymentMethod, Project } from "@/lib/types";
import { money, today } from "@/lib/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";

export function ProjectSaleFormModal({
  businessId,
  open,
  onClose,
  currency,
  onCreated,
  defaultProjectId,
}: {
  businessId: string | number;
  open: boolean;
  onClose: () => void;
  currency: string;
  onCreated?: (invoice: Invoice) => void;
  defaultProjectId?: number | string | null;
}) {
  const [projectId, setProjectId] = useState("");
  const [amount, setAmount] = useState("");
  const [description, setDescription] = useState("");
  const [date, setDate] = useState(today());
  const [method, setMethod] = useState<PaymentMethod>("bank_transfer");
  const [notes, setNotes] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  function defaultDescription(row: Project) {
    return `${row.work} — ${row.topic}`.slice(0, 255);
  }

  const projectsQuery = useQuery({
    queryKey: ["projects", String(businessId), "roster"],
    queryFn: async () => (await api.get<Paginated<Project>>(`/businesses/${businessId}/projects`, { params: { per_page: 100 } })).data.data,
    enabled: open,
  });

  useEffect(() => {
    if (open) {
      setProjectId(defaultProjectId ? String(defaultProjectId) : "");
      setAmount("");
      setDescription("");
      setDate(today());
      setMethod("bank_transfer");
      setNotes("");
      setErrors({});
    }
  }, [open, defaultProjectId]);

  const project = (projectsQuery.data ?? []).find((row) => row.id === Number(projectId));
  const amountExceedsDue = Boolean(project) && Number(amount) > project!.due_amount + 0.001;

  // Prefills the billed description from the project, but only while it still
  // matches an earlier project's default — once the seller types their own
  // wording (or picks a different project) it's left alone.
  useEffect(() => {
    if (project) setDescription((current) => (current === "" ? defaultDescription(project) : current));
  }, [project]);

  const mutation = useMutation({
    mutationFn: async () => {
      if (!project) throw new Error("Select a project.");
      const invoice = (await api.post<ApiMessage<{ invoice: Invoice }>>(`/businesses/${businessId}/invoices`, {
        project_id: project.id,
        invoice_date: date,
        status: "issued",
        discount_amount: 0,
        notes: notes || null,
        items: [{
          description: (description || defaultDescription(project)).slice(0, 255),
          quantity: 1,
          unit_price: Number(amount),
          discount_amount: 0,
          tax_rate: 0,
        }],
      })).data.invoice;

      await api.post(`/businesses/${businessId}/invoices/${invoice.id}/payments`, {
        payment_date: date,
        amount: Number(amount),
        method,
        notes: notes || null,
      });

      return (await api.get<{ data: Invoice }>(`/businesses/${businessId}/invoices/${invoice.id}`)).data.data;
    },
    onSuccess: async (invoice) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["invoices", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["projects", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["project", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["business-dashboard", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["portfolio-dashboard"] }),
        queryClient.invalidateQueries({ queryKey: ["customers", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["customer", String(businessId)] }),
      ]);
      toast.success("Payment recorded", { description: `${money(Number(amount), currency)} for ${project?.client_name}` });
      onCreated?.(invoice);
      onClose();
    },
    onError: (error) => {
      setErrors(fieldErrors(error));
      toast.error("Could not record this payment", { description: apiError(error) });
      // The due amount may have just changed (another payment recorded elsewhere) — refresh it.
      void queryClient.invalidateQueries({ queryKey: ["projects", String(businessId)] });
    },
  });

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Record a sale"
      description="Every sale here is a payment against a project's deal amount."
      footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="project-sale-form" loading={mutation.isPending} disabled={amountExceedsDue}>Record payment</Button></>}
    >
      <form id="project-sale-form" onSubmit={(event) => { event.preventDefault(); setErrors({}); mutation.mutate(); }} className="space-y-4">
        <FieldShell label="Project" htmlFor="project-sale-project" error={errors.project_id?.[0]} required>
          <Select id="project-sale-project" value={projectId} onChange={(event) => { setProjectId(event.target.value); setAmount(""); setDescription(""); }} required>
            <option value="">Select the project this payment is for</option>
            {(projectsQuery.data ?? []).map((row) => <option key={row.id} value={row.id}>{row.client_name} · {row.course}</option>)}
          </Select>
        </FieldShell>
        {project ? (
          <div className="rounded-2xl bg-[var(--surface-soft)] p-4 text-sm">
            <div className="flex items-center justify-between"><span className="text-[var(--ink-soft)]">Deal amount</span><span className="font-bold">{money(project.deal_amount, currency)}</span></div>
            <div className="mt-1.5 flex items-center justify-between"><span className="text-[var(--ink-soft)]">Already collected (paid so far)</span><span className="font-bold">− {money(project.collected_amount, currency)}</span></div>
            <div className="my-2 border-t border-[var(--line)]" />
            <div className="flex items-center justify-between"><span className="font-semibold">Due now</span><span className="font-black">{money(project.due_amount, currency)}</span></div>
            <p className="mt-2 text-xs leading-5 text-[var(--ink-soft)]">Due = deal amount minus everything already collected across every sale recorded for this project. The most you can record right now is {money(project.due_amount, currency)}.</p>
          </div>
        ) : null}
        {project ? (
          <FieldShell label="Billed as" htmlFor="project-sale-description" hint="What appears on the printed invoice — defaults to the topic, edit it for a shorter or different description">
            <Input id="project-sale-description" value={description} onChange={(event) => setDescription(event.target.value)} maxLength={255} placeholder={defaultDescription(project)} />
          </FieldShell>
        ) : null}
        <div className="grid gap-4 sm:grid-cols-2">
          <FieldShell label="Amount received" htmlFor="project-sale-amount" error={errors.amount?.[0] ?? (amountExceedsDue ? `Cannot exceed the due amount of ${money(project!.due_amount, currency)}.` : undefined)} required>
            <Input id="project-sale-amount" type="number" min="0.01" max={project ? project.due_amount : undefined} step="0.01" placeholder="0.00" value={amount} onChange={(event) => setAmount(event.target.value)} disabled={!project} required />
          </FieldShell>
          <FieldShell label="Date" htmlFor="project-sale-date" error={errors.invoice_date?.[0]} required>
            <Input id="project-sale-date" type="date" max={today()} value={date} onChange={(event) => setDate(event.target.value)} required />
          </FieldShell>
          <FieldShell label="Method" htmlFor="project-sale-method" error={errors.method?.[0]} required>
            <Select id="project-sale-method" value={method} onChange={(event) => setMethod(event.target.value as PaymentMethod)}><option value="bank_transfer">Bank transfer</option><option value="qr">QR payment</option><option value="cash">Cash</option><option value="wallet">Digital wallet</option><option value="card">Card</option><option value="cheque">Cheque</option><option value="other">Other</option></Select>
          </FieldShell>
        </div>
        <FieldShell label="Notes" htmlFor="project-sale-notes" error={errors.notes?.[0]}>
          <Textarea id="project-sale-notes" value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Any extra detail worth keeping" />
        </FieldShell>
      </form>
    </Modal>
  );
}
