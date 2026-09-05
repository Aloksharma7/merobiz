"use client";

import { CustomerFormModal } from "@/components/forms/customer-form-modal";
import { Badge, statusTone } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { PageLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { CustomerInvoiceProfile, CustomerProjectProfile, CustomerProjectRow } from "@/lib/types";
import { humanize, money, prettyDate, shortTopic } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Briefcase, CheckCircle2, CircleDollarSign, FolderKanban, Mail, PenTool, Phone, ReceiptText, Wallet2 } from "lucide-react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useState } from "react";

export default function CustomerDetailPage() {
  const { businessId, customerId } = useParams<{ businessId: string; customerId: string }>();
  const { getBusiness, can } = useBusinesses();
  const business = getBusiness(businessId);
  const canManage = can(business, "customers.manage");
  const [editOpen, setEditOpen] = useState(false);

  const query = useQuery({
    queryKey: ["customer", businessId, customerId],
    queryFn: async () => (await api.get<CustomerProjectProfile | CustomerInvoiceProfile>(`/businesses/${businessId}/customers/${customerId}`)).data,
    enabled: Boolean(business),
  });

  if (!business || query.isLoading) return <PageLoading />;
  if (query.isError || !query.data) return <ErrorState onRetry={() => void query.refetch()} />;

  const { customer } = query.data;
  const currency = business.currency;
  const isProjectProfile = "projects" in query.data;

  return (
    <div className="space-y-7">
      <Link href={`/b/${businessId}/customers`} className="inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--ink-soft)] hover:text-[var(--brand)]"><ArrowLeft size={15} />Back to customers</Link>

      <PageHeader
        eyebrow={business.name}
        title={customer.name}
        description={[customer.phone, customer.email].filter(Boolean).join(" · ") || undefined}
        actions={canManage ? <Button variant="secondary" onClick={() => setEditOpen(true)}>Edit customer</Button> : undefined}
      />

      <div className="flex flex-wrap items-center gap-3">
        <Badge tone={customer.active ? "success" : "neutral"}>{customer.active ? "Active" : "Inactive"}</Badge>
        {customer.phone ? <span className="flex items-center gap-1.5 text-xs text-[var(--ink-soft)]"><Phone size={13} />{customer.phone}</span> : null}
        {customer.email ? <span className="flex items-center gap-1.5 text-xs text-[var(--ink-soft)]"><Mail size={13} />{customer.email}</span> : null}
      </div>

      {isProjectProfile ? <ProjectProfileView profile={query.data as CustomerProjectProfile} businessId={businessId} currency={currency} /> : <InvoiceProfileView profile={query.data as CustomerInvoiceProfile} currency={currency} />}

      {customer.notes ? (
        <Card className="overflow-hidden">
          <CardHeader title="Notes" />
          <p className="whitespace-pre-wrap p-5 text-sm leading-6 sm:p-6">{customer.notes}</p>
        </Card>
      ) : null}

      <CustomerFormModal businessId={businessId} open={editOpen} customer={customer} onClose={() => setEditOpen(false)} />
    </div>
  );
}

function ProjectProfileView({ profile, businessId, currency }: { profile: CustomerProjectProfile; businessId: string; currency: string }) {
  const { stats, projects, payments } = profile;
  const ongoing = projects.filter((row) => row.work_status !== "approved" && row.work_status !== "cancelled");
  const completed = projects.filter((row) => row.work_status === "approved");
  const cancelled = projects.filter((row) => row.work_status === "cancelled");
  return (
    <>
      <section className="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <Stat label="Files" value={String(stats.total_projects)} icon={FolderKanban} />
        <Stat label="Completed" value={String(stats.completed_projects)} icon={CheckCircle2} />
        <Stat label="In progress" value={String(stats.in_progress_projects)} icon={Briefcase} />
        <Stat label="Still due" value={money(stats.total_due, currency)} icon={CircleDollarSign} emphasis={stats.total_due > 0} />
      </section>

      <Card className="overflow-hidden">
        <CardHeader title="Ongoing work" description={`${money(stats.total_deal_amount, currency)} total deal · ${money(stats.total_collected, currency)} collected so far`} />
        {ongoing.length ? <ProjectsTable rows={ongoing} businessId={businessId} currency={currency} /> : <CardBody><EmptyState icon={FolderKanban} title="Nothing ongoing" description="Files still in progress for this client will show up here." /></CardBody>}
      </Card>

      <Card className="overflow-hidden">
        <CardHeader title="Previous work" description="Files already approved and completed for this client." />
        {completed.length ? <ProjectsTable rows={completed} businessId={businessId} currency={currency} /> : <CardBody><EmptyState icon={CheckCircle2} title="No completed files yet" description="Approved files for this client will show up here." /></CardBody>}
      </Card>

      {cancelled.length ? (
        <Card className="overflow-hidden">
          <CardHeader title="Cancelled / refunded" description={stats.total_refunded > 0 ? `${money(stats.total_refunded, currency)} refunded to this client in total` : "Files closed before completion."} />
          <ProjectsTable rows={cancelled} businessId={businessId} currency={currency} showRefunded />
        </Card>
      ) : null}

      <Card className="overflow-hidden">
        <CardHeader title="Payment history" description={`${money(stats.total_collected, currency)} collected in total across all files`} />
        {payments.length ? (
          <div className="divide-y divide-[var(--line)]">
            {payments.map((payment) => (
              <Link href={payment.project_id ? `/b/${businessId}/projects/${payment.project_id}` : "#"} key={payment.id} className="flex items-center gap-3 px-5 py-3.5 text-sm hover:bg-[var(--surface-soft)]">
                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]"><Wallet2 size={16} /></span>
                <div className="min-w-0 flex-1">
                  <p className="truncate font-bold">{payment.project_topic ?? "Payment"}</p>
                  <p className="text-xs text-[var(--ink-soft)]">{prettyDate(payment.payment_date)} · {humanize(payment.method)}{payment.notes ? ` · ${payment.notes}` : ""}</p>
                </div>
                <p className="font-black text-[var(--brand)]">{money(payment.amount, currency)}</p>
              </Link>
            ))}
          </div>
        ) : <CardBody><EmptyState icon={Wallet2} title="No payments yet" description="Payments received from this client will show up here." /></CardBody>}
      </Card>
    </>
  );
}

function ProjectsTable({ rows, businessId, currency, showRefunded }: { rows: CustomerProjectRow[]; businessId: string; currency: string; showRefunded?: boolean }) {
  const router = useRouter();
  return (
    <>
      <div className="hidden overflow-x-auto md:block">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Topic</th><th className="px-4 py-3 font-bold">Status</th><th className="px-4 py-3 font-bold">Writer</th><th className="px-4 py-3 text-right font-bold">Deal</th><th className="px-4 py-3 text-right font-bold">Collected</th><th className="px-5 py-3 text-right font-bold">{showRefunded ? "Refunded" : "Due"}</th></tr></thead>
          <tbody className="divide-y divide-[var(--line)]">
            {rows.map((row) => (
              <tr key={row.id} className="cursor-pointer hover:bg-[var(--surface-soft)]" onClick={() => router.push(`/b/${businessId}/projects/${row.id}`)}>
                <td className="px-5 py-3.5"><Link href={`/b/${businessId}/projects/${row.id}`} onClick={(event) => event.stopPropagation()} className="block max-w-[280px] truncate font-bold hover:text-[var(--brand)]">{shortTopic(row.topic)}</Link><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{row.course} · {row.work}</p></td>
                <td className="px-4 py-3.5"><Badge tone={statusTone(row.work_status)}>{row.work_status}</Badge></td>
                <td className="px-4 py-3.5 text-xs text-[var(--ink-soft)]">{row.writer ? <span className="flex items-center gap-1.5"><PenTool size={12} />{row.writer.name}</span> : "Unassigned"}</td>
                <td className="px-4 py-3.5 text-right font-semibold">{money(row.deal_amount, currency)}</td>
                <td className="px-4 py-3.5 text-right font-semibold text-[var(--brand)]">{money(row.collected_amount, currency)}</td>
                <td className="px-5 py-3.5 text-right font-black">{money(showRefunded ? row.refunded_amount : row.due_amount, currency)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="divide-y divide-[var(--line)] md:hidden">
        {rows.map((row) => (
          <Link href={`/b/${businessId}/projects/${row.id}`} key={row.id} className="block p-4 hover:bg-[var(--surface-soft)]">
            <div className="flex items-center justify-between gap-3"><p className="truncate font-bold">{shortTopic(row.topic)}</p><Badge tone={statusTone(row.work_status)}>{row.work_status}</Badge></div>
            <p className="mt-0.5 text-xs text-[var(--ink-soft)]">{row.course} · {row.work}</p>
            <div className="mt-2 flex items-center justify-between text-sm"><span className="text-[var(--ink-soft)]">Collected {money(row.collected_amount, currency)} of {money(row.deal_amount, currency)}</span><span className="font-black">{money(showRefunded ? row.refunded_amount : row.due_amount, currency)} {showRefunded ? "refunded" : "due"}</span></div>
          </Link>
        ))}
      </div>
    </>
  );
}

function InvoiceProfileView({ profile, currency }: { profile: CustomerInvoiceProfile; currency: string }) {
  const { stats, invoices } = profile;
  return (
    <>
      <section className="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <Stat label="Invoices" value={String(stats.total_invoices)} icon={ReceiptText} />
        <Stat label="Total invoiced" value={money(stats.total_invoiced, currency)} icon={Wallet2} />
        <Stat label="Total paid" value={money(stats.total_paid, currency)} icon={CheckCircle2} />
        <Stat label="Outstanding" value={money(stats.outstanding, currency)} icon={CircleDollarSign} emphasis={stats.outstanding > 0} />
      </section>

      <Card className="overflow-hidden">
        <CardHeader title="Recent invoices" />
        {invoices.length ? (
          <div className="divide-y divide-[var(--line)]">
            {invoices.map((invoice) => (
              <div key={invoice.id} className="flex items-center gap-3 px-5 py-3.5 text-sm">
                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]"><ReceiptText size={16} /></span>
                <div className="min-w-0 flex-1"><p className="truncate font-bold">{invoice.invoice_number}</p><p className="text-xs text-[var(--ink-soft)]">{prettyDate(invoice.invoice_date)} · {humanize(invoice.status)}</p></div>
                <div className="text-right"><p className="font-black">{money(invoice.total_amount, currency)}</p>{invoice.balance_amount > 0 ? <p className="text-xs text-[var(--danger)]">{money(invoice.balance_amount, currency)} due</p> : null}</div>
              </div>
            ))}
          </div>
        ) : <CardBody><EmptyState icon={ReceiptText} title="No invoices yet" description="Sales made to this customer will show up here." /></CardBody>}
      </Card>
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
