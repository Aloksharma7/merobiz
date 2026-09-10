"use client";

import { DateRangeControl, defaultRange, type DateRangeValue } from "@/components/dashboard/date-range-control";
import { WriterFormModal } from "@/components/forms/writer-form-modal";
import { WriterPaymentFormModal } from "@/components/forms/writer-payment-form-modal";
import { Badge, statusTone } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { PageLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api, apiError } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { WriterProfile } from "@/lib/types";
import { money, prettyDate, shortTopic } from "@/lib/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Briefcase, CircleDollarSign, FolderKanban, Mail, Phone, Plus, Trash2, Wallet2 } from "lucide-react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useState } from "react";
import { toast } from "sonner";

export default function WriterDetailPage() {
  const { businessId, writerId } = useParams<{ businessId: string; writerId: string }>();
  const { getBusiness, can } = useBusinesses();
  const business = getBusiness(businessId);
  const canManage = can(business, "writers.manage");
  const [editOpen, setEditOpen] = useState(false);
  const [payOpen, setPayOpen] = useState(false);
  const [range, setRange] = useState<DateRangeValue>(defaultRange);
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ["writer", businessId, writerId, range],
    queryFn: async () => (await api.get<WriterProfile>(`/businesses/${businessId}/writers/${writerId}`, { params: range })).data,
    enabled: Boolean(business),
  });

  const deletePaymentMutation = useMutation({
    mutationFn: async (paymentId: number) => api.delete(`/businesses/${businessId}/writers/${writerId}/payments/${paymentId}`),
    onSuccess: async () => { await queryClient.invalidateQueries({ queryKey: ["writer", businessId, writerId] }); toast.success("Payment undone"); },
    onError: (error) => toast.error("Could not undo this payment", { description: apiError(error) }),
  });

  if (!business || query.isLoading) return <PageLoading />;
  if (query.isError || !query.data) return <ErrorState onRetry={() => void query.refetch()} />;

  const { writer, stats, projects, payments, period } = query.data;
  const currency = business.currency;
  const current = projects.filter((row) => row.is_current);
  const past = projects.filter((row) => !row.is_current);
  const payableProjects = current.filter((row) => (row.writer_due_amount ?? 0) > 0);

  return (
    <div className="space-y-7">
      <Link href={`/b/${businessId}/writers`} className="inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--ink-soft)] hover:text-[var(--brand)]"><ArrowLeft size={15} />Back to writers</Link>

      <PageHeader
        eyebrow={business.name}
        title={writer.name}
        description={[writer.phone, writer.email].filter(Boolean).join(" · ") || undefined}
        actions={canManage ? <><Button variant="secondary" onClick={() => setEditOpen(true)}>Edit writer</Button>{payableProjects.length ? <Button leftIcon={<Plus size={17} />} onClick={() => setPayOpen(true)}>Pay writer</Button> : null}</> : undefined}
      />

      <div className="flex flex-wrap items-center gap-3">
        <Badge tone={writer.active ? "success" : "neutral"}>{writer.active ? "Active" : "Inactive"}</Badge>
        {writer.phone ? <span className="flex items-center gap-1.5 text-xs text-[var(--ink-soft)]"><Phone size={13} />{writer.phone}</span> : null}
        {writer.email ? <span className="flex items-center gap-1.5 text-xs text-[var(--ink-soft)]"><Mail size={13} />{writer.email}</span> : null}
      </div>

      <section className="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <Stat label="Files handled" value={String(stats.total_projects)} icon={FolderKanban} />
        <Stat label="Currently working on" value={String(stats.current_projects)} icon={Briefcase} />
        <Stat label="Total paid (all time)" value={money(stats.total_paid, currency)} icon={Wallet2} />
        <Stat label="Still due" value={money(stats.total_due, currency)} icon={CircleDollarSign} emphasis={stats.total_due > 0} />
      </section>

      {writer.notes ? (
        <Card className="overflow-hidden">
          <CardHeader title="Notes" />
          <p className="whitespace-pre-wrap p-5 text-sm leading-6 sm:p-6">{writer.notes}</p>
        </Card>
      ) : null}

      <Card className="overflow-hidden">
        <CardHeader
          title="Payments"
          description={`${money(stats.period_paid, currency)} paid in ${period.label}`}
          action={<DateRangeControl value={range} onChange={setRange} showLast30Days={false} />}
        />
        {payments.length ? (
          <div className="divide-y divide-[var(--line)]">
            {payments.map((payment) => (
              <div key={payment.id} className="flex items-center gap-3 px-5 py-3.5 text-sm hover:bg-[var(--surface-soft)]">
                <Link href={`/b/${businessId}/projects/${payment.project_id}`} className="flex min-w-0 flex-1 items-center gap-3">
                  <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]"><Wallet2 size={16} /></span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-bold">{payment.client_name ?? "File"}</p>
                    <p className="text-xs text-[var(--ink-soft)]">{prettyDate(payment.paid_on)}{payment.notes ? ` · ${payment.notes}` : ""}</p>
                  </div>
                </Link>
                <p className="font-black">{money(payment.amount, currency)}</p>
                {canManage ? (
                  <button
                    type="button"
                    disabled={deletePaymentMutation.isPending}
                    onClick={() => { if (window.confirm("Undo this payment? This removes it entirely, as if it never happened.")) deletePaymentMutation.mutate(payment.id); }}
                    className="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-[var(--ink-soft)] transition hover:bg-[var(--danger-soft)] hover:text-[var(--danger)] disabled:opacity-40"
                    aria-label="Undo this payment"
                  >
                    <Trash2 size={15} />
                  </button>
                ) : null}
              </div>
            ))}
          </div>
        ) : <CardBody><EmptyState icon={Wallet2} title="No payments in this period" description="Payments made to this writer in the selected range will show up here." /></CardBody>}
      </Card>

      <Card className="overflow-hidden">
        <CardHeader title="Currently working on" description="Files where this writer is the active assignment." />
        {current.length ? <ProjectsTable rows={current} businessId={businessId} currency={currency} /> : <EmptyState icon={FolderKanban} title="Nothing assigned right now" description="This writer isn't the current writer on any file." />}
      </Card>

      <Card className="overflow-hidden">
        <CardHeader title="Past files" description="Files this writer previously worked on before being reassigned." />
        {past.length ? <ProjectsTable rows={past} businessId={businessId} currency={currency} showAssignedRange /> : <EmptyState icon={FolderKanban} title="No past files" description="Files this writer has been reassigned away from will show up here." />}
      </Card>

      <WriterFormModal businessId={businessId} open={editOpen} writer={writer} onClose={() => setEditOpen(false)} />
      <WriterPaymentFormModal businessId={businessId} writerId={writerId} open={payOpen} onClose={() => setPayOpen(false)} currency={currency} currentProjects={payableProjects} />
    </div>
  );
}

function ProjectsTable({ rows, businessId, currency, showAssignedRange }: { rows: WriterProfile["projects"]; businessId: string; currency: string; showAssignedRange?: boolean }) {
  const router = useRouter();
  return (
    <>
      <div className="hidden overflow-x-auto md:block">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Client</th><th className="px-4 py-3 font-bold">Status</th>{showAssignedRange ? <th className="px-4 py-3 font-bold">Assigned</th> : null}<th className="px-4 py-3 text-right font-bold">Total amount to pay</th><th className="px-4 py-3 text-right font-bold">Paid</th>{!showAssignedRange ? <th className="px-5 py-3 text-right font-bold">Still due</th> : null}</tr></thead>
          <tbody className="divide-y divide-[var(--line)]">
            {rows.map((row) => (
              <tr key={row.id} className="cursor-pointer hover:bg-[var(--surface-soft)]" onClick={() => router.push(`/b/${businessId}/projects/${row.id}`)}>
                <td className="px-5 py-3.5"><Link href={`/b/${businessId}/projects/${row.id}`} onClick={(event) => event.stopPropagation()} className="block max-w-[220px] truncate font-bold hover:text-[var(--brand)]">{row.client_name}</Link><p className="mt-0.5 max-w-[220px] truncate font-bold">{shortTopic(row.topic)}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{row.course} · {row.work}</p></td>
                <td className="px-4 py-3.5"><Badge tone={statusTone(row.work_status)}>{row.work_status}</Badge></td>
                {showAssignedRange ? <td className="px-4 py-3.5 text-xs text-[var(--ink-soft)]">{row.assigned_from ? prettyDate(row.assigned_from) : "—"} – {row.assigned_to ? prettyDate(row.assigned_to) : "now"}</td> : null}
                <td className="px-4 py-3.5 text-right font-semibold">{money(row.writer_payment_amount, currency)}</td>
                <td className="px-4 py-3.5 text-right font-semibold text-[var(--brand)]">{money(row.writer_paid_amount, currency)}</td>
                {!showAssignedRange ? <td className="px-5 py-3.5 text-right font-black">{money(row.writer_due_amount ?? 0, currency)}</td> : null}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="divide-y divide-[var(--line)] md:hidden">
        {rows.map((row) => (
          <Link href={`/b/${businessId}/projects/${row.id}`} key={row.id} className="block p-4 hover:bg-[var(--surface-soft)]">
            <div className="flex items-center justify-between gap-3"><p className="truncate font-bold">{row.client_name}</p><Badge tone={statusTone(row.work_status)}>{row.work_status}</Badge></div>
            <p className="mt-0.5 truncate font-bold">{shortTopic(row.topic)}</p>
            <p className="mt-0.5 text-xs text-[var(--ink-soft)]">{row.course} · {row.work}</p>
            <div className="mt-2 flex items-center justify-between text-sm"><span className="text-[var(--ink-soft)]">Paid {money(row.writer_paid_amount, currency)} of {money(row.writer_payment_amount, currency)}</span>{row.writer_due_amount !== null ? <span className="font-black">{money(row.writer_due_amount, currency)} due</span> : null}</div>
          </Link>
        ))}
      </div>
    </>
  );
}

function Stat({ label, value, icon: Icon, emphasis }: { label: string; value: string; icon: typeof FolderKanban; emphasis?: boolean }) {
  return (
    <Card className={emphasis ? "border-[var(--brand)] bg-[var(--brand-deep)] p-4 text-[var(--on-brand-deep)]" : "p-4"}>
      <div className={`grid h-9 w-9 place-items-center rounded-xl ${emphasis ? "bg-[var(--on-brand-deep)]/10 text-[var(--on-brand-deep)]" : "bg-[var(--brand-soft)] text-[var(--brand)]"}`}><Icon size={17} /></div>
      <p className={`mt-3 text-[10px] font-bold uppercase tracking-[0.11em] ${emphasis ? "text-[var(--on-brand-deep)]/55" : "text-[var(--ink-soft)]"}`}>{label}</p>
      <p className="mt-1 truncate text-lg font-black tracking-[-0.03em]">{value}</p>
    </Card>
  );
}
