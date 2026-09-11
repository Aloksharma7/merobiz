"use client";

import { DateRangeControl, defaultRange, type DateRangeValue } from "@/components/dashboard/date-range-control";
import { Badge, statusTone } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { PageLoading } from "@/components/ui/loading";
import { api } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import type { WriterProfile } from "@/lib/types";
import { money, prettyDate, shortTopic } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { Briefcase, CircleDollarSign, FolderKanban, LogOut, Mail, Phone, Wallet2 } from "lucide-react";

export default function WriterDashboardPage() {
  const { user, isLoading: authLoading, logout } = useAuth();
  const router = useRouter();
  const [range, setRange] = useState<DateRangeValue>(defaultRange);

  useEffect(() => {
    if (authLoading) return;
    if (!user) { router.replace("/login"); return; }
    // This page is only for a writer login — anyone else (owner/admin/employee)
    // gets sent to the workspace that actually applies to their account.
    if (user.workspace?.mode !== "writer") router.replace("/");
  }, [authLoading, router, user]);

  const query = useQuery({
    queryKey: ["writer-self", range],
    queryFn: async () => (await api.get<WriterProfile>("/writer/dashboard", { params: range })).data,
    enabled: Boolean(user) && user?.workspace?.mode === "writer",
  });

  if (authLoading || !user || user.workspace?.mode !== "writer" || query.isLoading) return <PageLoading />;
  if (query.isError || !query.data) return <div className="mx-auto max-w-3xl p-6"><ErrorState onRetry={() => void query.refetch()} /></div>;

  const { writer, business, stats, projects, payments, period } = query.data;
  const currency = business.currency;
  const current = projects.filter((row) => row.is_current);
  const past = projects.filter((row) => !row.is_current);

  return (
    <div className="min-h-screen bg-[var(--canvas)]">
      <header className="sticky top-0 z-10 border-b border-[var(--line)] bg-white/95 backdrop-blur-xl">
        <div className="mx-auto flex max-w-4xl items-center justify-between gap-3 px-4 py-4 sm:px-6">
          <div className="min-w-0">
            <p className="truncate text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--ink-soft)]">{business.name}</p>
            <h1 className="truncate text-lg font-black tracking-[-0.02em]">{writer.name}</h1>
          </div>
          <Button variant="secondary" size="sm" leftIcon={<LogOut size={15} />} onClick={() => void logout()}>Sign out</Button>
        </div>
      </header>

      <main className="mx-auto max-w-4xl space-y-7 px-4 py-6 sm:px-6 sm:py-8">
        <div className="flex flex-wrap items-center gap-3">
          <Badge tone={writer.active ? "success" : "neutral"}>{writer.active ? "Active" : "Inactive"}</Badge>
          {writer.phone ? <span className="flex items-center gap-1.5 text-xs text-[var(--ink-soft)]"><Phone size={13} />{writer.phone}</span> : null}
          {writer.email ? <span className="flex items-center gap-1.5 text-xs text-[var(--ink-soft)]"><Mail size={13} />{writer.email}</span> : null}
        </div>

        <section className="grid grid-cols-2 gap-4 xl:grid-cols-4">
          <Stat label="Files handled" value={String(stats.total_projects)} icon={FolderKanban} />
          <Stat label="Currently working on" value={String(stats.current_projects)} icon={Briefcase} />
          <Stat label="Total paid (all time)" value={money(stats.total_paid, currency)} icon={Wallet2} />
          <Stat label="Still due to you" value={money(stats.total_due, currency)} icon={CircleDollarSign} emphasis={stats.total_due > 0} />
        </section>

        <Card className="overflow-hidden">
          <CardHeader
            title="Payments received"
            description={`${money(stats.period_paid, currency)} paid in ${period.label}`}
            action={<DateRangeControl value={range} onChange={setRange} showLast30Days={false} />}
          />
          {payments.length ? (
            <div className="divide-y divide-[var(--line)]">
              {payments.map((payment) => (
                <div key={payment.id} className="flex items-center gap-3 px-5 py-3.5 text-sm">
                  <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]"><Wallet2 size={16} /></span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-bold">{payment.client_name ?? "File"}</p>
                    <p className="text-xs text-[var(--ink-soft)]">{prettyDate(payment.paid_on)}{payment.notes ? ` · ${payment.notes}` : ""}</p>
                  </div>
                  <p className="font-black">{money(payment.amount, currency)}</p>
                </div>
              ))}
            </div>
          ) : <CardBody><EmptyState icon={Wallet2} title="No payments in this period" description="Payments you've received will show up here." /></CardBody>}
        </Card>

        <Card className="overflow-hidden">
          <CardHeader title="Currently working on" description="Files where you're the active writer." />
          {current.length ? <ProjectsList rows={current} currency={currency} /> : <EmptyState icon={FolderKanban} title="Nothing assigned right now" description="You aren't the current writer on any file." />}
        </Card>

        <Card className="overflow-hidden">
          <CardHeader title="Previous files" description="Files you worked on before being reassigned." />
          {past.length ? <ProjectsList rows={past} currency={currency} showAssignedRange /> : <EmptyState icon={FolderKanban} title="No previous files yet" description="Files you've been reassigned away from will show up here." />}
        </Card>
      </main>
    </div>
  );
}

function ProjectsList({ rows, currency, showAssignedRange }: { rows: WriterProfile["projects"]; currency: string; showAssignedRange?: boolean }) {
  const router = useRouter();
  return (
    <>
      <div className="hidden overflow-x-auto md:block">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Client</th><th className="px-4 py-3 font-bold">Status</th>{showAssignedRange ? <th className="px-4 py-3 font-bold">Assigned</th> : null}<th className="px-4 py-3 text-right font-bold">Total amount to you</th><th className="px-4 py-3 text-right font-bold">Paid</th>{!showAssignedRange ? <th className="px-5 py-3 text-right font-bold">Still due</th> : null}</tr></thead>
          <tbody className="divide-y divide-[var(--line)]">
            {rows.map((row) => (
              <tr key={row.id} className="cursor-pointer transition hover:bg-[var(--surface-soft)]" onClick={() => router.push(`/writer/projects/${row.id}`)}>
                <td className="px-5 py-3.5"><p className="block max-w-[220px] truncate font-bold">{row.client_name}</p><p className="mt-0.5 max-w-[220px] truncate font-bold">{shortTopic(row.topic)}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{row.course} · {row.work}</p></td>
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
          <div key={row.id} className="cursor-pointer p-4" onClick={() => router.push(`/writer/projects/${row.id}`)}>
            <div className="flex items-center justify-between gap-3"><p className="truncate font-bold">{row.client_name}</p><Badge tone={statusTone(row.work_status)}>{row.work_status}</Badge></div>
            <p className="mt-0.5 truncate font-bold">{shortTopic(row.topic)}</p>
            <p className="mt-0.5 text-xs text-[var(--ink-soft)]">{row.course} · {row.work}</p>
            <div className="mt-2 flex items-center justify-between text-sm"><span className="text-[var(--ink-soft)]">Paid {money(row.writer_paid_amount, currency)} of {money(row.writer_payment_amount, currency)}</span>{row.writer_due_amount !== null ? <span className="font-black">{money(row.writer_due_amount, currency)} due</span> : null}</div>
          </div>
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
