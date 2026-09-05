"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import { PROJECT_WORK_STATUSES } from "@/lib/project-status";
import type { ApiMessage, Invoice, Paginated, PaymentMethod, Project, ProjectWorkStatus, Writer } from "@/lib/types";
import { money, today } from "@/lib/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState, type FormEvent } from "react";
import { toast } from "sonner";

const blank = {
  client_name: "", client_phone: "", client_email: "", started_on: today(), topic: "", course: "", work: "",
  work_status: "started" as ProjectWorkStatus, deadline: "", deal_amount: "", writer_payment_amount: "0", writer_id: "",
  initial_payment_amount: "", initial_payment_method: "bank_transfer" as PaymentMethod,
};

export function ProjectFormModal({ businessId, open, onClose, project, currency = "NPR" }: { businessId: string | number; open: boolean; onClose: () => void; project?: Project | null; currency?: string }) {
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  const writersQuery = useQuery({
    queryKey: ["writers", String(businessId), "roster"],
    queryFn: async () => (await api.get<Paginated<Writer>>(`/businesses/${businessId}/writers`, { params: { active: true, per_page: 100 } })).data.data,
    enabled: open,
  });

  useEffect(() => {
    if (open) {
      setForm(project ? {
        client_name: project.client_name,
        client_phone: project.client_phone ?? "",
        client_email: project.client_email ?? "",
        started_on: project.started_on ?? today(),
        topic: project.topic,
        course: project.course,
        work: project.work,
        work_status: project.work_status,
        deadline: project.deadline ?? "",
        deal_amount: String(project.deal_amount),
        writer_payment_amount: String(project.writer_payment_amount),
        writer_id: project.current_writer ? String(project.current_writer.id) : "",
        initial_payment_amount: "",
        initial_payment_method: "bank_transfer",
      } : blank);
      setErrors({});
    }
  }, [open, project]);

  const initialPaymentAmount = Number(form.initial_payment_amount || 0);
  const initialPaymentExceedsDeal = initialPaymentAmount > 0 && Number(form.deal_amount || 0) > 0 && initialPaymentAmount > Number(form.deal_amount) + 0.001;

  const mutation = useMutation({
    mutationFn: async () => {
      const { initial_payment_amount: _initialPaymentAmount, initial_payment_method: _initialPaymentMethod, ...projectFields } = form;
      const payload = {
        ...projectFields,
        client_phone: form.client_phone || null,
        client_email: form.client_email || null,
        deadline: form.deadline || null,
        deal_amount: Number(form.deal_amount),
        writer_payment_amount: Number(form.writer_payment_amount || 0),
        writer_id: form.writer_id ? Number(form.writer_id) : null,
      };
      const path = project ? `/businesses/${businessId}/projects/${project.id}` : `/businesses/${businessId}/projects`;
      const result = (project
        ? await api.put<ApiMessage<{ project: Project }>>(path, payload)
        : await api.post<ApiMessage<{ project: Project }>>(path, payload)).data;

      let paymentError: string | null = null;
      if (!project && initialPaymentAmount > 0) {
        try {
          const invoice = (await api.post<ApiMessage<{ invoice: Invoice }>>(`/businesses/${businessId}/invoices`, {
            project_id: result.project.id,
            invoice_date: form.started_on,
            status: "issued",
            discount_amount: 0,
            items: [{
              description: `${form.work} — ${form.topic}`.slice(0, 255),
              quantity: 1,
              unit_price: initialPaymentAmount,
              discount_amount: 0,
              tax_rate: 0,
            }],
          })).data.invoice;
          await api.post(`/businesses/${businessId}/invoices/${invoice.id}/payments`, {
            payment_date: form.started_on,
            amount: initialPaymentAmount,
            method: form.initial_payment_method,
          });
        } catch (error) {
          paymentError = apiError(error);
        }
      }

      return { ...result, paymentError };
    },
    onSuccess: async (data) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["projects", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["project", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["invoices", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["business-dashboard", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["portfolio-dashboard"] }),
        queryClient.invalidateQueries({ queryKey: ["customers", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["customer", String(businessId)] }),
      ]);
      if (data.paymentError) {
        toast.success("Project created");
        toast.error("Initial payment could not be recorded", { description: `${data.paymentError} You can record it from the project page instead.` });
      } else if (!project && initialPaymentAmount > 0) {
        toast.success("Project created", { description: `Initial payment of ${money(initialPaymentAmount, currency)} recorded.` });
      } else {
        toast.success(project ? "Project updated" : "Project created");
      }
      onClose();
    },
    onError: (error) => {
      setErrors(fieldErrors(error));
      toast.error("Could not save project", { description: apiError(error) });
    },
  });

  function update<K extends keyof typeof form>(key: K, value: (typeof form)[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    mutation.mutate();
  }

  return (
    <Modal open={open} onClose={onClose} title={project ? "Edit project" : "New project"} description="Client, writer and deal details for this project." size="lg" footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="project-form" loading={mutation.isPending} disabled={initialPaymentExceedsDeal}>Save project</Button></>}>
      <form id="project-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
        <FieldShell label="Client name" htmlFor="project-client-name" error={errors.client_name?.[0]} required><Input id="project-client-name" value={form.client_name} onChange={(event) => update("client_name", event.target.value)} placeholder="e.g. Sushant Basnet" autoFocus required /></FieldShell>
        <FieldShell label="Client number" htmlFor="project-client-phone" error={errors.client_phone?.[0]}><Input id="project-client-phone" value={form.client_phone} onChange={(event) => update("client_phone", event.target.value)} placeholder="98XXXXXXXX" /></FieldShell>
        <FieldShell label="Client email" htmlFor="project-client-email" error={errors.client_email?.[0]} hint="Optional"><Input id="project-client-email" type="email" value={form.client_email} onChange={(event) => update("client_email", event.target.value)} placeholder="name@gmail.com" /></FieldShell>
        <FieldShell label="Started on" htmlFor="project-started-on" error={errors.started_on?.[0]} hint="Backdate for an existing file" required><Input id="project-started-on" type="date" max={today()} value={form.started_on} onChange={(event) => update("started_on", event.target.value)} required /></FieldShell>
        <FieldShell label="Topic" htmlFor="project-topic" error={errors.topic?.[0]} required className="sm:col-span-2"><Textarea id="project-topic" className="min-h-24" value={form.topic} onChange={(event) => update("topic", event.target.value)} placeholder="Full topic or title" required /></FieldShell>
        <FieldShell label="Course" htmlFor="project-course" error={errors.course?.[0]} required><Input id="project-course" value={form.course} onChange={(event) => update("course", event.target.value)} placeholder="e.g. MBA" required /></FieldShell>
        <FieldShell label="Work" htmlFor="project-work" error={errors.work?.[0]} required><Input id="project-work" value={form.work} onChange={(event) => update("work", event.target.value)} placeholder="e.g. Thesis, Assignment" required /></FieldShell>
        <FieldShell label="Work status" htmlFor="project-status" error={errors.work_status?.[0]}><Select id="project-status" value={form.work_status} onChange={(event) => update("work_status", event.target.value as ProjectWorkStatus)}>{PROJECT_WORK_STATUSES.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}</Select></FieldShell>
        <FieldShell label="Deadline" htmlFor="project-deadline" error={errors.deadline?.[0]} hint="Optional"><Input id="project-deadline" type="date" value={form.deadline} onChange={(event) => update("deadline", event.target.value)} /></FieldShell>
        <FieldShell label="Assigned writer" htmlFor="project-writer" error={errors.writer_id?.[0]} hint="Optional">
          <Select id="project-writer" value={form.writer_id} onChange={(event) => update("writer_id", event.target.value)}>
            <option value="">No writer assigned</option>
            {(writersQuery.data ?? []).map((writer) => <option key={writer.id} value={writer.id}>{writer.name}</option>)}
          </Select>
        </FieldShell>
        <FieldShell label="Deal amount" htmlFor="project-deal" error={errors.deal_amount?.[0]} required><Input id="project-deal" type="number" min="0" step="0.01" placeholder="0.00" value={form.deal_amount} onChange={(event) => update("deal_amount", event.target.value)} required /></FieldShell>
        <FieldShell label="Writer payment" htmlFor="project-writer-payment" error={errors.writer_payment_amount?.[0]} hint="What you owe the writer"><Input id="project-writer-payment" type="number" min="0" step="0.01" placeholder="0.00" value={form.writer_payment_amount} onChange={(event) => update("writer_payment_amount", event.target.value)} /></FieldShell>
        {!project ? (
          <>
            <FieldShell label="Initial payment" htmlFor="project-initial-payment" error={initialPaymentExceedsDeal ? `Cannot exceed the deal amount of ${money(Number(form.deal_amount || 0), currency)}.` : undefined} hint="Optional — record any amount already received when creating this file">
              <Input id="project-initial-payment" type="number" min="0" step="0.01" placeholder="0.00" value={form.initial_payment_amount} onChange={(event) => update("initial_payment_amount", event.target.value)} />
            </FieldShell>
            {initialPaymentAmount > 0 ? (
              <FieldShell label="Payment method" htmlFor="project-initial-payment-method">
                <Select id="project-initial-payment-method" value={form.initial_payment_method} onChange={(event) => update("initial_payment_method", event.target.value as PaymentMethod)}><option value="bank_transfer">Bank transfer</option><option value="qr">QR payment</option><option value="cash">Cash</option><option value="wallet">Digital wallet</option><option value="card">Card</option><option value="cheque">Cheque</option><option value="other">Other</option></Select>
              </FieldShell>
            ) : null}
          </>
        ) : null}
      </form>
    </Modal>
  );
}
