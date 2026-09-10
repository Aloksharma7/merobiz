"use client";

import { ProjectFormModal } from "@/components/forms/project-form-modal";
import { Badge, statusTone } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { PageLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api, apiError, fieldErrors } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import { PROJECT_WORK_STATUSES } from "@/lib/project-status";
import type { Paginated, Project, ProjectWorkStatus, Writer } from "@/lib/types";
import { money, prettyDate, today } from "@/lib/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, ArrowLeft, CircleDollarSign, HandCoins, PenTool, Plus, ReceiptText, Trash2, Undo2 } from "lucide-react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useState } from "react";
import { toast } from "sonner";

export default function ProjectDetailPage() {
  const { businessId, projectId } = useParams<{ businessId: string; projectId: string }>();
  const router = useRouter();
  const { getBusiness, can } = useBusinesses();
  const business = getBusiness(businessId);
  const canManage = can(business, "writers.manage");
  const queryClient = useQueryClient();
  const [editOpen, setEditOpen] = useState(false);
  const [deleteConfirmation, setDeleteConfirmation] = useState("");
  const [approvedOn, setApprovedOn] = useState(today());
  const [profitAmount, setProfitAmount] = useState("");
  const [profitNotes, setProfitNotes] = useState("");
  const [refundedOn, setRefundedOn] = useState(today());
  const [refundAmount, setRefundAmount] = useState("");
  const [refundNotes, setRefundNotes] = useState("");
  const [reassignWriterId, setReassignWriterId] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const detailQuery = useQuery({
    queryKey: ["project", businessId, projectId],
    queryFn: async () => (await api.get<{ data: Project }>(`/businesses/${businessId}/projects/${projectId}`)).data.data,
    enabled: Boolean(business),
  });
  const writersQuery = useQuery({
    queryKey: ["writers", businessId, "roster"],
    queryFn: async () => (await api.get<Paginated<Writer>>(`/businesses/${businessId}/writers`, { params: { active: true, per_page: 100 } })).data.data,
  });

  const project = detailQuery.data;

  async function invalidate() {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ["projects", businessId] }),
      queryClient.invalidateQueries({ queryKey: ["project", businessId, projectId] }),
      queryClient.invalidateQueries({ queryKey: ["business-dashboard", businessId] }),
      queryClient.invalidateQueries({ queryKey: ["portfolio-dashboard"] }),
    ]);
  }

  const approvalMutation = useMutation({
    mutationFn: async () => (await api.post(`/businesses/${businessId}/projects/${projectId}/profit-approvals`, { approved_on: approvedOn, amount: Number(profitAmount), notes: profitNotes || null })).data,
    onSuccess: async () => { await invalidate(); setProfitAmount(""); setProfitNotes(""); toast.success("Profit approved"); },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not approve profit", { description: apiError(error) }); },
  });

  const refundMutation = useMutation({
    mutationFn: async () => (await api.post(`/businesses/${businessId}/projects/${projectId}/refunds`, { refunded_on: refundedOn, amount: Number(refundAmount), notes: refundNotes || null })).data,
    onSuccess: async () => { await invalidate(); setRefundAmount(""); setRefundNotes(""); toast.success("Refund recorded"); },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not record refund", { description: apiError(error) }); },
  });

  const deleteApprovalMutation = useMutation({
    mutationFn: async (approvalId: number) => api.delete(`/businesses/${businessId}/projects/${projectId}/profit-approvals/${approvalId}`),
    onSuccess: async () => { await invalidate(); toast.success("Profit approval undone"); },
    onError: (error) => toast.error("Could not undo this approval", { description: apiError(error) }),
  });

  const deleteRefundMutation = useMutation({
    mutationFn: async (refundId: number) => api.delete(`/businesses/${businessId}/projects/${projectId}/refunds/${refundId}`),
    onSuccess: async () => { await invalidate(); toast.success("Refund undone"); },
    onError: (error) => toast.error("Could not undo this refund", { description: apiError(error) }),
  });

  const deleteProjectMutation = useMutation({
    mutationFn: async () => api.delete(`/businesses/${businessId}/projects/${projectId}`),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["projects", businessId] });
      toast.success("Project permanently deleted");
      router.replace(`/b/${businessId}/projects`);
    },
    onError: (error) => toast.error("Could not delete this project", { description: apiError(error) }),
  });

  const writerMutation = useMutation({
    mutationFn: async () => (await api.post(`/businesses/${businessId}/projects/${projectId}/writer`, { writer_id: Number(reassignWriterId), date: today() })).data,
    onSuccess: async () => { await invalidate(); setReassignWriterId(""); toast.success("Writer reassigned"); },
    onError: (error) => toast.error("Could not reassign writer", { description: apiError(error) }),
  });

  const statusMutation = useMutation({
    mutationFn: async (status: ProjectWorkStatus) => (await api.put(`/businesses/${businessId}/projects/${projectId}`, { work_status: status })).data,
    onSuccess: async () => { await invalidate(); toast.success("Status updated"); },
    onError: (error) => toast.error("Could not update status", { description: apiError(error) }),
  });

  if (!business || detailQuery.isLoading) return <PageLoading />;
  if (detailQuery.isError || !project) return <ErrorState onRetry={() => void detailQuery.refetch()} />;

  const currency = business.currency;
  const isOverdue = Boolean(project.deadline) && project.deadline! < today() && !["submitted", "approved", "cancelled"].includes(project.work_status);

  return (
    <div className="space-y-7">
      <Link href={`/b/${businessId}/projects`} className="inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--ink-soft)] hover:text-[var(--brand)]"><ArrowLeft size={15} />Back to projects</Link>

      <PageHeader
        eyebrow={`${project.course} · ${project.work}`}
        title={project.client_name}
        description={[project.client_phone, project.client_email].filter(Boolean).join(" · ") || undefined}
        actions={canManage ? <Button variant="secondary" onClick={() => setEditOpen(true)}>Edit project</Button> : undefined}
      />

      <Card className="overflow-hidden">
        <CardHeader title="Topic" />
        <CardBody><p className="whitespace-pre-wrap text-lg font-bold leading-7 tracking-[-0.01em]">{project.topic}</p></CardBody>
      </Card>

      <div className="flex flex-wrap items-center gap-3">
        <Badge tone={statusTone(project.work_status)}>{project.work_status}</Badge>
        {canManage ? (
          <Select
            value={project.work_status}
            onChange={(event) => statusMutation.mutate(event.target.value as ProjectWorkStatus)}
            disabled={statusMutation.isPending}
            className="h-9 w-56 text-xs"
            aria-label="Change work status"
          >
            {PROJECT_WORK_STATUSES.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
          </Select>
        ) : null}
        {project.started_on ? <span className="text-xs text-[var(--ink-soft)]">Started {prettyDate(project.started_on)}</span> : null}
        {project.deadline ? (
          <span className="flex items-center gap-1.5 text-xs font-semibold">
            {isOverdue ? <Badge tone="danger">Overdue</Badge> : null}
            <span className={isOverdue ? "text-[var(--danger)]" : "text-[var(--ink-soft)]"}>Deadline {prettyDate(project.deadline)}</span>
          </span>
        ) : null}
      </div>

      <section className={`grid grid-cols-2 gap-4 ${project.work_status === "cancelled" && project.refunded_amount > 0 ? "xl:grid-cols-4" : "xl:grid-cols-3"}`}>
        <Stat label="Deal amount" value={money(project.deal_amount, currency)} />
        <Stat label="Collected" value={money(project.collected_amount, currency)} />
        {project.work_status === "cancelled" ? (
          <Stat label="Refunded to client" value={money(project.refunded_amount, currency)} emphasis={project.refunded_amount > 0} />
        ) : (
          <Stat label="Due from client" value={money(project.due_amount, currency)} emphasis={project.due_amount > 0} />
        )}
        {project.work_status === "cancelled" && project.refunded_amount > 0 ? (
          <Stat label="Kept after refund" value={money(project.net_collected_amount, currency)} />
        ) : null}
      </section>

      <section className="grid grid-cols-2 gap-4 xl:grid-cols-2">
        <Stat label="Total amount to pay writer" value={money(project.writer_payment_amount, currency)} />
        <Stat label="Still due to writer" value={money(project.writer_due_amount, currency)} emphasis={project.writer_due_amount > 0} />
      </section>

      <div className="grid gap-4 xl:grid-cols-2">
        <Card className="overflow-hidden">
          <CardHeader title={<span className="flex items-center gap-2"><PenTool size={16} />Writer</span>} description="Current assignment and reassignment history." />
          <CardBody className="space-y-4">
            <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-[var(--line)] p-3.5">
              <span>
                <span className="block text-sm font-bold">{project.current_writer ? project.current_writer.name : "No writer assigned"}</span>
                {project.current_writer ? <span className="mt-0.5 block text-xs text-[var(--ink-soft)]">Assigned since {prettyDate(project.current_writer.assigned_from)}</span> : null}
              </span>
              <div className="ml-auto flex items-center gap-2">
                <Select value={reassignWriterId} onChange={(event) => setReassignWriterId(event.target.value)} className="w-44">
                  <option value="">Reassign to…</option>
                  {(writersQuery.data ?? []).filter((writer) => writer.id !== project.current_writer?.id).map((writer) => <option key={writer.id} value={writer.id}>{writer.name}</option>)}
                </Select>
                <Button size="sm" variant="secondary" disabled={!reassignWriterId} loading={writerMutation.isPending} onClick={() => writerMutation.mutate()}>Assign</Button>
              </div>
            </div>
            {project.writer_history && project.writer_history.filter((entry) => !entry.current).length ? (
              <div className="space-y-1.5">
                {project.writer_history.filter((entry) => !entry.current).map((entry) => (
                  <p key={entry.id} className="text-xs text-[var(--ink-soft)]">{entry.writer.name} · {prettyDate(entry.assigned_from)} – {entry.assigned_to ? prettyDate(entry.assigned_to) : "now"}</p>
                ))}
              </div>
            ) : null}
          </CardBody>
        </Card>

        {business.full_control ? (
          <Card className="overflow-hidden">
            <CardHeader title={<span className="flex items-center gap-2"><CircleDollarSign size={16} />Profit approval</span>} description="Only counts toward this business's expected profit once approved here. Writer payments are deducted automatically elsewhere — don't subtract them from this figure yourself." />
            <CardBody className="space-y-4">
              {project.profit_approvals?.length ? (
                <div className="space-y-2">
                  {project.profit_approvals.map((approval) => (
                    <div key={approval.id} className="flex items-center justify-between gap-3 rounded-xl border border-[var(--line)] p-3 text-sm">
                      <span>{prettyDate(approval.approved_on)}{approval.notes ? ` · ${approval.notes}` : ""}</span>
                      <div className="flex shrink-0 items-center gap-2">
                        <span className="font-black">{money(approval.amount, currency)}</span>
                        <button
                          type="button"
                          disabled={deleteApprovalMutation.isPending}
                          onClick={() => { if (window.confirm("Undo this profit approval? This removes it entirely, as if it never happened.")) deleteApprovalMutation.mutate(approval.id); }}
                          className="grid h-8 w-8 place-items-center rounded-lg text-[var(--ink-soft)] transition hover:bg-[var(--danger-soft)] hover:text-[var(--danger)] disabled:opacity-40"
                          aria-label="Undo this profit approval"
                        >
                          <Trash2 size={14} />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              ) : <p className="text-xs text-[var(--ink-soft)]">Nothing approved yet.</p>}
              <div className="space-y-3 rounded-2xl bg-[var(--brand-deep)] p-4 text-[var(--on-brand-deep)]">
                <div className="flex flex-wrap items-end gap-3">
                  <FieldShell label="Date" htmlFor="project-approve-date" error={errors.approved_on?.[0]}><Input id="project-approve-date" type="date" max={today()} value={approvedOn} onChange={(event) => setApprovedOn(event.target.value)} /></FieldShell>
                  <FieldShell label="Profit amount" htmlFor="project-approve-amount" error={errors.amount?.[0]}><Input id="project-approve-amount" type="number" min="0.01" step="0.01" placeholder="0.00" value={profitAmount} onChange={(event) => setProfitAmount(event.target.value)} className="w-32" /></FieldShell>
                  <Button size="sm" variant="secondary" disabled={!profitAmount} loading={approvalMutation.isPending} onClick={() => approvalMutation.mutate()}>Approve profit</Button>
                </div>
                <FieldShell label="Notes" htmlFor="project-approve-notes"><Textarea id="project-approve-notes" className="min-h-16 bg-white text-[var(--ink)]" value={profitNotes} onChange={(event) => setProfitNotes(event.target.value)} placeholder="e.g. Deal amount, before writer payment" /></FieldShell>
              </div>
            </CardBody>
          </Card>
        ) : null}
      </div>

      {canManage ? (
        <Card className="overflow-hidden">
          <CardHeader
            title={<span className="flex items-center gap-2"><Undo2 size={16} />Refunds</span>}
            description="Record money given back to the client if this file was closed or aborted before completion."
          />
          <CardBody className="space-y-4">
            {project.refunds?.length ? (
              <div className="space-y-2">
                {project.refunds.map((refund) => (
                  <div key={refund.id} className="flex items-center justify-between gap-3 rounded-xl border border-[var(--line)] p-3 text-sm">
                    <span>{prettyDate(refund.refunded_on)}{refund.notes ? ` · ${refund.notes}` : ""}</span>
                    <div className="flex shrink-0 items-center gap-2">
                      <span className="font-black">{money(refund.amount, currency)}</span>
                      <button
                        type="button"
                        disabled={deleteRefundMutation.isPending}
                        onClick={() => { if (window.confirm("Undo this refund? This removes it entirely, as if it never happened.")) deleteRefundMutation.mutate(refund.id); }}
                        className="grid h-8 w-8 place-items-center rounded-lg text-[var(--ink-soft)] transition hover:bg-[var(--danger-soft)] hover:text-[var(--danger)] disabled:opacity-40"
                        aria-label="Undo this refund"
                      >
                        <Trash2 size={14} />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            ) : <p className="text-xs text-[var(--ink-soft)]">No refunds recorded yet.</p>}
            {project.collected_amount - project.refunded_amount > 0.01 ? (
              <div className="space-y-3 rounded-2xl bg-[var(--surface-soft)] p-4">
                <div className="flex flex-wrap items-end gap-3">
                  <FieldShell label="Date" htmlFor="project-refund-date" error={errors.refunded_on?.[0]}><Input id="project-refund-date" type="date" max={today()} value={refundedOn} onChange={(event) => setRefundedOn(event.target.value)} /></FieldShell>
                  <FieldShell label="Refund amount" htmlFor="project-refund-amount" error={errors.amount?.[0]} hint={`Up to ${money(project.collected_amount - project.refunded_amount, currency)}`}><Input id="project-refund-amount" type="number" min="0.01" max={project.collected_amount - project.refunded_amount} step="0.01" placeholder="0.00" value={refundAmount} onChange={(event) => setRefundAmount(event.target.value)} className="w-36" /></FieldShell>
                  <Button size="sm" variant="secondary" disabled={!refundAmount} loading={refundMutation.isPending} onClick={() => refundMutation.mutate()}>Record refund</Button>
                </div>
                <FieldShell label="Notes" htmlFor="project-refund-notes"><Textarea id="project-refund-notes" className="min-h-16" value={refundNotes} onChange={(event) => setRefundNotes(event.target.value)} placeholder="e.g. Client cancelled after first draft" /></FieldShell>
              </div>
            ) : null}
          </CardBody>
        </Card>
      ) : null}

      <Card className="overflow-hidden">
        <CardHeader
          title={<span className="flex items-center gap-2"><ReceiptText size={16} />Sales</span>}
          description="Every payment received against this project, with what's been paid and what's still due."
          action={canManage ? <Link href={`/b/${businessId}/sales?project=${project.id}`}><Button size="sm" leftIcon={<Plus size={14} />}>Record payment</Button></Link> : undefined}
        />
        {project.invoices?.length ? (
          <>
            <div className="hidden overflow-x-auto md:block">
              <table className="w-full min-w-[560px] text-left text-sm">
                <thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Sale</th><th className="px-4 py-3 font-bold">Date</th><th className="px-4 py-3 font-bold">Status</th><th className="px-4 py-3 text-right font-bold">Amount</th><th className="px-4 py-3 text-right font-bold">Paid</th><th className="px-5 py-3 text-right font-bold">Balance</th></tr></thead>
                <tbody className="divide-y divide-[var(--line)]">
                  {project.invoices.map((invoice) => (
                    <tr key={invoice.id} className="hover:bg-[var(--surface-soft)]">
                      <td className="px-5 py-3.5 font-bold">{invoice.invoice_number}</td>
                      <td className="px-4 py-3.5 text-[var(--ink-soft)]">{prettyDate(invoice.invoice_date)}</td>
                      <td className="px-4 py-3.5"><Badge tone={statusTone(invoice.status)}>{invoice.status}</Badge></td>
                      <td className="px-4 py-3.5 text-right font-semibold">{money(invoice.total_amount, currency)}</td>
                      <td className="px-4 py-3.5 text-right font-semibold text-[var(--brand)]">{money(invoice.paid_amount, currency)}</td>
                      <td className="px-5 py-3.5 text-right font-black">{money(invoice.balance_amount, currency)}</td>
                    </tr>
                  ))}
                </tbody>
                <tfoot><tr className="border-t border-[var(--line)] bg-[var(--surface-soft)] font-black"><td className="px-5 py-3" colSpan={3}>Total</td><td className="px-4 py-3 text-right">{money(project.deal_amount, currency)}</td><td className="px-4 py-3 text-right text-[var(--brand)]">{money(project.collected_amount, currency)}</td><td className="px-5 py-3 text-right">{money(project.due_amount, currency)}</td></tr></tfoot>
              </table>
            </div>
            <div className="divide-y divide-[var(--line)] md:hidden">
              {project.invoices.map((invoice) => (
                <div key={invoice.id} className="p-4">
                  <div className="flex items-center justify-between gap-3"><p className="font-bold">{invoice.invoice_number}</p><Badge tone={statusTone(invoice.status)}>{invoice.status}</Badge></div>
                  <p className="mt-0.5 text-xs text-[var(--ink-soft)]">{prettyDate(invoice.invoice_date)}</p>
                  <div className="mt-2 flex items-center justify-between text-sm"><span className="text-[var(--ink-soft)]">Paid {money(invoice.paid_amount, currency)}</span><span className="font-black">{money(invoice.balance_amount, currency)} due</span></div>
                </div>
              ))}
            </div>
          </>
        ) : (
          <CardBody><EmptyState icon={HandCoins} title="No sale created yet" description="The deal amount stays fully due until a sale is recorded for this project." action={canManage ? <Link href={`/b/${businessId}/sales?project=${project.id}`}><Button leftIcon={<Plus size={16} />}>Record payment</Button></Link> : undefined} /></CardBody>
        )}
      </Card>

      {canManage ? (
        <Card className="border-[var(--danger)]/30">
          <CardHeader title={<span className="flex items-center gap-2 text-[var(--danger)]"><AlertTriangle size={18} />Danger zone</span>} description="This cannot be undone." />
          <CardBody className="space-y-3">
            <p className="text-sm leading-6 text-[var(--ink-soft)]">
              Permanently deletes <strong>{project.client_name}</strong>&apos;s file — its writer assignment history, profit approvals and refunds all go with it.
              {project.invoices?.length ? " Any sale already recorded for this project stays in Sales, just no longer linked to this file." : null}
            </p>
            <FieldShell label={`Type "${project.client_name}" to confirm`} htmlFor="delete-project-confirm">
              <Input id="delete-project-confirm" value={deleteConfirmation} onChange={(event) => setDeleteConfirmation(event.target.value)} placeholder={project.client_name} />
            </FieldShell>
            <Button type="button" variant="danger" leftIcon={<Trash2 size={16} />} disabled={deleteConfirmation !== project.client_name} loading={deleteProjectMutation.isPending} onClick={() => deleteProjectMutation.mutate()}>
              Delete this project permanently
            </Button>
          </CardBody>
        </Card>
      ) : null}

      <ProjectFormModal businessId={businessId} open={editOpen} project={project} onClose={() => setEditOpen(false)} currency={business.currency} />
    </div>
  );
}

function Stat({ label, value, emphasis }: { label: string; value: string; emphasis?: boolean }) {
  return (
    <Card className={emphasis ? "border-[var(--brand)] bg-[var(--brand-deep)] p-4 text-[var(--on-brand-deep)]" : "p-4"}>
      <p className={`text-[10px] font-bold uppercase tracking-[0.11em] ${emphasis ? "text-[var(--on-brand-deep)]/55" : "text-[var(--ink-soft)]"}`}>{label}</p>
      <p className="mt-1.5 truncate text-lg font-black tracking-[-0.03em]">{value}</p>
    </Card>
  );
}
