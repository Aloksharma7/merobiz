"use client";

import { DateRangeControl, defaultRange, type DateRangeValue } from "@/components/dashboard/date-range-control";
import { ClosePeriodModal } from "@/components/forms/close-period-modal";
import { ProfitDistributionModal } from "@/components/forms/profit-distribution-modal";
import { Badge, statusTone } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { PageLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { Paginated, ProfitAllocation, ProfitDistribution, ProfitLossReport, ProfitPeriod } from "@/lib/types";
import { humanize, money, number, prettyDate } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { ArrowDown, ArrowRight, Banknote, CalendarCheck2, CheckCircle2, CircleDollarSign, HandCoins, Landmark, LockKeyhole, PieChart, ReceiptText, Scale, WalletCards } from "lucide-react";
import { useParams } from "next/navigation";
import { useMemo, useState } from "react";

export default function ReportsPage() {
  const { businessId } = useParams<{ businessId: string }>();
  const { getBusiness } = useBusinesses();
  const business = getBusiness(businessId);
  const [range, setRange] = useState<DateRangeValue>(defaultRange);
  const [closingOpen, setClosingOpen] = useState(false);
  const [distributionOpen, setDistributionOpen] = useState(false);

  const profitLoss = useQuery({
    queryKey: ["profit-loss", businessId, range],
    queryFn: async () => (await api.get<ProfitLossReport>(`/businesses/${businessId}/reports/profit-loss`, { params: range })).data,
    enabled: Boolean(business),
  });
  const periods = useQuery({
    queryKey: ["profit-periods", businessId],
    queryFn: async () => (await api.get<Paginated<ProfitPeriod>>(`/businesses/${businessId}/profit-periods`, { params: { per_page: 24 } })).data,
    enabled: Boolean(business),
  });
  const distributions = useQuery({
    queryKey: ["profit-distributions", businessId],
    queryFn: async () => (await api.get<Paginated<ProfitDistribution>>(`/businesses/${businessId}/profit-distributions`, { params: { per_page: 30 } })).data,
    enabled: Boolean(business),
  });

  const allocations = useMemo<ProfitAllocation[]>(() => periods.data?.data.flatMap((period) => period.allocations ?? []) ?? [], [periods.data]);
  const outstandingAllocations = allocations.filter((row) => row.remaining_amount > 0);
  const canClose = business ? ["owner", "admin"].includes(business.my_role) : false;
  const allocated = allocations.reduce((sum, row) => sum + row.allocated_amount, 0);
  const distributed = allocations.reduce((sum, row) => sum + row.distributed_amount, 0);
  const outstanding = allocations.reduce((sum, row) => sum + row.remaining_amount, 0);

  if (!business) return <PageLoading />;
  if (profitLoss.isError || periods.isError || distributions.isError) {
    return <ErrorState onRetry={() => void Promise.all([profitLoss.refetch(), periods.refetch(), distributions.refetch()])} />;
  }
  if (!profitLoss.data) return <PageLoading />;

  const report = profitLoss.data.report;
  const margin = report.net_sales !== 0 ? (report.net_profit / report.net_sales) * 100 : 0;
  const currency = business.currency;

  return (
    <div className="space-y-7">
      <PageHeader
        eyebrow={business.name}
        title="Reports & owner profit"
        description="Live performance stays separate from finalized partner allocations and money actually paid."
        actions={canClose ? <>
          <Button variant="secondary" leftIcon={<LockKeyhole size={16} />} onClick={() => setClosingOpen(true)}>Close period</Button>
          <Button leftIcon={<Banknote size={17} />} disabled={!outstandingAllocations.length} onClick={() => setDistributionOpen(true)}>Record payout</Button>
        </> : undefined}
      />

      <DateRangeControl value={range} onChange={setRange} />

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <ReportMetric label="Live net sales" value={money(report.net_sales, currency)} note="Revenue after discounts" icon={ReceiptText} />
        <ReportMetric label="Live net profit" value={money(report.net_profit, currency)} note={`${number(margin, 1)}% net margin`} icon={CircleDollarSign} emphasis />
        <ReportMetric label="Cash collected" value={money(report.cash_collected, currency)} note="Payments received in range" icon={Landmark} />
        <ReportMetric label="Receivables" value={money(report.receivables, currency)} note="Due on invoices dated in this period" icon={HandCoins} />
      </section>

      <section className="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(330px,0.85fr)]">
        <Card>
          <CardHeader title="Profit bridge" description={`Provisional result for ${profitLoss.data.period.label}.`} action={<Badge tone="warning">Live estimate</Badge>} />
          <CardBody>
            <ProfitLine label="Net sales" value={report.net_sales} currency={currency} strong />
            <ProfitLine label="Direct cost of sales" value={-report.cost_of_sales} currency={currency} subdued />
            <ProfitLine label="Gross profit" value={report.gross_profit} currency={currency} subtotal />
            <ProfitLine label="Approved operating expenses" value={-report.expenses} currency={currency} subdued />
            <ProfitLine label="Payroll paid" value={-report.payroll_cost} currency={currency} subdued />
            <div className="mt-3 flex items-center justify-between gap-4 rounded-2xl bg-[var(--brand-deep)] px-4 py-4 text-[var(--on-brand-deep)]">
              <div><p className="text-xs font-semibold text-[var(--on-brand-deep)]/60">Business net profit</p><p className="mt-1 text-[11px] text-[var(--on-brand-deep)]/45">Before partner distribution</p></div>
              <p className="text-xl font-black tracking-[-0.03em]">{money(report.net_profit, currency)}</p>
            </div>
            <div className="mt-4 flex items-start gap-3 rounded-2xl border border-[var(--line)] bg-[var(--surface-soft)] p-4 text-sm leading-6 text-[var(--ink-soft)]">
              <Scale size={18} className="mt-0.5 shrink-0 text-[var(--brand)]" />
              <p>This is a live calculation. Close a period only after invoices, costs and approved expenses are complete; the close creates dated partner allocations. Commission accrued so far ({money(report.commissions, currency)}) is estimated and only reduces profit once actually paid from a team member's Payroll page.</p>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="Finalized owner money" description="Across the closed periods shown below." />
          <CardBody className="space-y-4">
            <AllocationStat label="Partner profit allocated" value={allocated} currency={currency} icon={PieChart} />
            <AllocationStat label="Actually distributed" value={distributed} currency={currency} icon={CheckCircle2} />
            <AllocationStat label="Still payable" value={outstanding} currency={currency} icon={WalletCards} highlight />
            <div className="rounded-2xl bg-[var(--accent-soft)] p-4 text-sm leading-6 text-[#725020]">
              Profit allocated is not the same as cash received. A payout is recorded separately, preserving a clear partner ledger.
            </div>
          </CardBody>
        </Card>
      </section>

      <Card className="overflow-hidden">
        <CardHeader title="Closed profit periods" description="Immutable snapshots used for owner profit allocation." action={<span className="text-xs font-semibold text-[var(--ink-soft)]">{periods.data?.meta.total ?? 0} periods</span>} />
        {periods.data?.data.length ? (
          <div className="divide-y divide-[var(--line)]">
            {periods.data.data.map((period) => (
              <article key={period.id} className="p-5 sm:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                  <div className="flex items-start gap-3">
                    <span className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[var(--brand-soft)] text-[var(--brand)]"><CalendarCheck2 size={20} /></span>
                    <div><div className="flex flex-wrap items-center gap-2"><h3 className="font-black">{prettyDate(period.start_date)} – {prettyDate(period.end_date)}</h3><Badge tone={statusTone(period.status)}>{period.status}</Badge></div><p className="mt-1 text-xs text-[var(--ink-soft)]">Closed {prettyDate(period.closed_at)}{period.closed_by?.name ? ` by ${period.closed_by.name}` : ""}</p>{period.notes ? <p className="mt-2 text-sm text-[var(--ink-soft)]">{period.notes}</p> : null}</div>
                  </div>
                  <div className="grid grid-cols-2 gap-x-6 gap-y-2 rounded-2xl bg-[var(--surface-soft)] px-4 py-3 text-right sm:grid-cols-3">
                    <SmallValue label="Net sales" value={money(period.net_sales, currency)} />
                    <SmallValue label="Expenses" value={money(period.expenses + period.payroll_cost, currency)} />
                    <SmallValue label="Net profit" value={money(period.net_profit, currency)} strong />
                  </div>
                </div>
                <div className="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                  {period.allocations.map((allocation) => (
                    <div key={allocation.id} className="rounded-2xl border border-[var(--line)] p-4">
                      <div className="flex items-start justify-between gap-3"><div><p className="font-bold">{allocation.name}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{number(allocation.effective_profit_share_percent, 2)}% effective profit share</p></div><ArrowRight size={16} className="mt-1 text-[var(--ink-soft)]" /></div>
                      <div className="mt-4 grid grid-cols-3 gap-2 text-xs"><SmallValue label="Allocated" value={money(allocation.allocated_amount, currency)} /><SmallValue label="Paid" value={money(allocation.distributed_amount, currency)} /><SmallValue label="Due" value={money(allocation.remaining_amount, currency)} strong={allocation.remaining_amount > 0} /></div>
                    </div>
                  ))}
                </div>
              </article>
            ))}
          </div>
        ) : <EmptyState icon={LockKeyhole} title="No closed periods yet" description="The live report is available now. Close a completed month to freeze the result and allocate partner profit." action={canClose ? <Button onClick={() => setClosingOpen(true)}>Close first period</Button> : undefined} />}
      </Card>

      <Card className="overflow-hidden">
        <CardHeader title="Profit payouts" description="Money actually paid against finalized partner allocations." />
        {distributions.data?.data.length ? (
          <>
            <div className="hidden overflow-x-auto md:block">
              <table className="w-full min-w-[760px] text-left text-sm">
                <thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Date</th><th className="px-4 py-3 font-bold">Partner</th><th className="px-4 py-3 font-bold">Method</th><th className="px-4 py-3 font-bold">Reference</th><th className="px-5 py-3 text-right font-bold">Amount</th></tr></thead>
                <tbody className="divide-y divide-[var(--line)]">{distributions.data.data.map((row) => <tr key={row.id} className="hover:bg-[var(--surface-soft)]"><td className="px-5 py-3.5 font-semibold">{prettyDate(row.distribution_date)}</td><td className="px-4 py-3.5"><p className="font-bold">{row.user_name || "Partner"}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">Recorded by {row.recorded_by || "—"}</p></td><td className="px-4 py-3.5"><Badge>{humanize(row.method)}</Badge></td><td className="px-4 py-3.5 text-[var(--ink-soft)]">{row.reference || "—"}</td><td className="px-5 py-3.5 text-right font-black">{money(row.amount, currency)}</td></tr>)}</tbody>
              </table>
            </div>
            <div className="divide-y divide-[var(--line)] md:hidden">{distributions.data.data.map((row) => <div key={row.id} className="p-4"><div className="flex items-start justify-between gap-3"><div><p className="font-bold">{row.user_name || "Partner"}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{prettyDate(row.distribution_date)} · Recorded by {row.recorded_by || "—"}</p></div><Badge>{humanize(row.method)}</Badge></div><div className="mt-3 flex items-center justify-between"><p className="text-xs text-[var(--ink-soft)]">{row.reference || "No reference"}</p><p className="text-lg font-black">{money(row.amount, currency)}</p></div></div>)}</div>
          </>
        ) : <EmptyState icon={Banknote} title="No profit payouts recorded" description="Payouts appear here after money is paid against a closed-period partner allocation." />}
      </Card>

      <ClosePeriodModal businessId={businessId} open={closingOpen} onClose={() => setClosingOpen(false)} />
      <ProfitDistributionModal businessId={businessId} open={distributionOpen} onClose={() => setDistributionOpen(false)} allocations={allocations} currency={currency} />
    </div>
  );
}

function ReportMetric({ label, value, note, icon: Icon, emphasis = false }: { label: string; value: string; note: string; icon: typeof ReceiptText; emphasis?: boolean }) {
  return <Card className={emphasis ? "border-[var(--brand)] bg-[var(--brand-deep)] text-[var(--on-brand-deep)]" : "p-0"}><div className="flex h-full min-h-32 items-start gap-4 p-5"><span className={`grid h-10 w-10 shrink-0 place-items-center rounded-2xl ${emphasis ? "bg-[var(--on-brand-deep)]/10 text-[var(--accent)]" : "bg-[var(--brand-soft)] text-[var(--brand)]"}`}><Icon size={19} /></span><div className="min-w-0"><p className={`text-[10px] font-bold uppercase tracking-[0.12em] ${emphasis ? "text-[var(--on-brand-deep)]/55" : "text-[var(--ink-soft)]"}`}>{label}</p><p className="mt-2 truncate text-xl font-black tracking-[-0.035em]">{value}</p><p className={`mt-1.5 text-xs ${emphasis ? "text-[var(--on-brand-deep)]/55" : "text-[var(--ink-soft)]"}`}>{note}</p></div></div></Card>;
}

function ProfitLine({ label, value, currency, strong = false, subdued = false, subtotal = false }: { label: string; value: number; currency: string; strong?: boolean; subdued?: boolean; subtotal?: boolean }) {
  return <div className={`flex items-center justify-between gap-4 border-b border-dashed border-[var(--line)] py-3.5 ${subtotal ? "mb-1 rounded-xl bg-[var(--surface-soft)] px-3" : "px-1"}`}><div className="flex items-center gap-2.5">{subdued ? <ArrowDown size={14} className="text-[var(--danger)]" /> : null}<span className={`text-sm ${strong || subtotal ? "font-bold" : "text-[var(--ink-soft)]"}`}>{label}</span></div><span className={`text-sm ${strong || subtotal ? "font-black" : "font-semibold"}`}>{value < 0 ? "−" : ""}{money(Math.abs(value), currency)}</span></div>;
}

function AllocationStat({ label, value, currency, icon: Icon, highlight = false }: { label: string; value: number; currency: string; icon: typeof PieChart; highlight?: boolean }) {
  return <div className={`flex items-center gap-3 rounded-2xl border p-4 ${highlight ? "border-[var(--brand)] bg-[var(--brand-soft)]" : "border-[var(--line)]"}`}><span className="grid h-10 w-10 place-items-center rounded-xl bg-white text-[var(--brand)] shadow-sm"><Icon size={18} /></span><div className="min-w-0"><p className="text-xs text-[var(--ink-soft)]">{label}</p><p className="mt-1 truncate font-black">{money(value, currency)}</p></div></div>;
}

function SmallValue({ label, value, strong = false }: { label: string; value: string; strong?: boolean }) {
  return <div><p className="text-[10px] font-semibold uppercase tracking-[0.08em] text-[var(--ink-soft)]">{label}</p><p className={`mt-1 whitespace-nowrap text-sm ${strong ? "font-black text-[var(--brand-deep)]" : "font-semibold"}`}>{value}</p></div>;
}
