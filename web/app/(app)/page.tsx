"use client";

import { BusinessPerformanceCard } from "@/components/dashboard/business-performance-card";
import { DateRangeControl, defaultRange, type DateRangeValue } from "@/components/dashboard/date-range-control";
import { MetricCard } from "@/components/dashboard/metric-card";
import { TrendChart } from "@/components/dashboard/trend-chart";
import { BusinessFormModal } from "@/components/forms/business-form-modal";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { PageLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import type { PortfolioDashboard } from "@/lib/types";
import { money, prettyDate } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { Building2, CircleDollarSign, Clock3, HandCoins, Landmark, Plus, ReceiptText, TrendingUp, UsersRound } from "lucide-react";
import { useState } from "react";
import { useEffect } from "react";
import { useRouter } from "next/navigation";

export default function PortfolioPage() {
  const { user } = useAuth();
  const router = useRouter();
  const employeeWorkspace = user?.workspace?.mode === "employee";
  const [range, setRange] = useState<DateRangeValue>(defaultRange);
  const [businessModal, setBusinessModal] = useState(false);
  const query = useQuery({
    queryKey: ["portfolio-dashboard", range],
    queryFn: async () => (await api.get<PortfolioDashboard>("/portfolio/dashboard", { params: range })).data,
    enabled: Boolean(user) && !employeeWorkspace,
  });

  useEffect(() => {
    if (employeeWorkspace && user?.workspace?.business_id) {
      router.replace(`/b/${user?.workspace?.business_id}`);
    }
  }, [employeeWorkspace, router, user?.workspace?.business_id]);

  if (employeeWorkspace) return <PageLoading />;

  if (query.isLoading) return <PageLoading />;
  if (query.isError || !query.data) return <ErrorState onRetry={() => void query.refetch()} />;

  const data = query.data;
  const currency = data.reporting_currency ?? data.businesses[0]?.currency ?? "NPR";
  const ownerMode = data.mode === "owner";

  return (
    <div className="space-y-7">
      <PageHeader
        eyebrow={ownerMode ? "Portfolio owner" : "Administration"}
        title={ownerMode ? "All your businesses, one clear answer" : "Business overview"}
        description={ownerMode ? "See each business and the amount attributable to your ownership." : "See the businesses assigned to you and monitor sales, staff and daily performance from one place."}
        actions={data.can_create_business ? <Button leftIcon={<Plus size={17} />} onClick={() => setBusinessModal(true)}>Add business</Button> : undefined}
      />

      <DateRangeControl value={range} onChange={setRange} />

      {data.mixed_currencies ? <div role="status" className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950"><strong>Mixed currencies:</strong> combined cards are not exchange-rate converted. Open each business for exact figures.</div> : null}

      {data.businesses.length === 0 ? (
        <Card><EmptyState icon={Building2} title="Add your first business" description="Each business remains separate while the owner gets a combined view." action={data.can_create_business ? <Button leftIcon={<Plus size={17} />} onClick={() => setBusinessModal(true)}>Add business</Button> : undefined} /></Card>
      ) : (
        <>
          <section>
            <div className="mb-3 flex items-end justify-between gap-4">
              <div><h2 className="text-lg font-black tracking-[-0.025em]">Businesses</h2><p className="mt-1 text-sm text-[var(--ink-soft)]">Open a business to manage its sales, staff and daily operations.</p></div>
              <Badge tone="neutral">{data.businesses.length} assigned</Badge>
            </div>
            <div className="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">{data.businesses.map((row) => <BusinessPerformanceCard key={row.id} row={row} />)}</div>
          </section>

          <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Portfolio summary">
            {ownerMode ? <MetricCard emphasis label="Your estimated profit" value={data.summary.attributable_profit ?? 0} currency={currency} icon={CircleDollarSign} hint="Ownership-adjusted live estimate" /> : <MetricCard emphasis label="Business net profit" value={data.summary.net_profit} currency={currency} icon={CircleDollarSign} hint="Sales after costs, expenses and commissions" />}
            <MetricCard label="Combined net sales" value={data.summary.net_sales} currency={currency} icon={TrendingUp} hint={`${data.summary.invoice_count} invoices`} />
            <MetricCard label="Cash collected" value={data.summary.cash_collected} currency={currency} icon={Landmark} hint="Payments received in this period" />
            <MetricCard label="Customer receivables" value={data.summary.receivables} currency={currency} icon={HandCoins} hint="Balance on open invoices" />
          </section>

          {ownerMode ? (
            <section className="grid gap-3 rounded-[var(--radius)] border border-[var(--line)] bg-white p-3 shadow-[var(--shadow-sm)] sm:grid-cols-2 xl:grid-cols-4">
              {[
                ["Business net profit", data.summary.net_profit, "After costs, expenses and commissions"],
                ["Operating expenses", data.summary.expenses, "Approved expenses in this period"],
                ["Profit received", data.summary.distributed_profit ?? 0, "Partner distributions paid to you"],
                ["Profit still payable", data.summary.outstanding_profit ?? 0, "Closed allocations not yet distributed"],
              ].map(([label, value, note]) => <div key={String(label)} className="rounded-2xl bg-[var(--surface-soft)] px-4 py-3.5"><p className="text-[10px] font-bold uppercase tracking-[0.12em] text-[var(--ink-soft)]">{String(label)}</p><p className="mt-1.5 text-lg font-black tracking-[-0.03em]">{money(Number(value), currency)}</p><p className="mt-1 text-[11px] leading-4 text-[var(--ink-soft)]">{String(note)}</p></div>)}
            </section>
          ) : null}

          <section className="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1.65fr)_minmax(330px,0.85fr)]">
            <TrendChart data={data.trend} currency={currency} portfolio={ownerMode} />
            <Card className="overflow-hidden">
              <CardHeader title="Top sellers" description="Sales performance in this period" />
              <CardBody className="p-0">
                {data.top_sellers.length ? <div className="divide-y divide-[var(--line)]">{data.top_sellers.slice(0, 6).map((seller, index) => <div key={seller.user_id} className="flex items-center gap-3 px-5 py-3.5"><span className="w-4 text-center text-xs font-black text-[var(--ink-soft)]">{index + 1}</span><span className="grid h-9 w-9 place-items-center rounded-xl bg-[var(--brand-soft)] text-xs font-black text-[var(--brand-deep)]">{seller.initials}</span><div className="min-w-0 flex-1"><p className="truncate text-sm font-bold">{seller.name}</p><p className="text-xs text-[var(--ink-soft)]">{seller.invoice_count} invoices</p></div><p className="text-sm font-black">{money(seller.sales, currency)}</p></div>)}</div> : <EmptyState icon={UsersRound} title="No sales yet" description="Seller performance appears as soon as sales are entered." />}
              </CardBody>
            </Card>
          </section>

          <Card className="overflow-hidden">
            <CardHeader title="Recent activity" description="Important changes across assigned businesses" />
            {data.recent_activity.length ? <div className="divide-y divide-[var(--line)]">{data.recent_activity.slice(0, 8).map((activity) => <div key={activity.id} className="flex items-start gap-3 px-5 py-3.5 sm:px-6"><div className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-[var(--surface-soft)] text-[var(--brand)]"><Clock3 size={15} /></div><div className="min-w-0 flex-1"><p className="text-sm font-semibold">{activity.label}</p><p className="mt-0.5 truncate text-xs text-[var(--ink-soft)]">{activity.user_name ?? "System"} · {activity.business_name ?? "Portfolio"}</p></div><time className="shrink-0 text-xs text-[var(--ink-soft)]">{prettyDate(activity.created_at, "dd MMM, HH:mm")}</time></div>)}</div> : <EmptyState icon={ReceiptText} title="No activity yet" description="Sales, payments, expenses and settings changes will appear here." />}
          </Card>
        </>
      )}
      <BusinessFormModal open={businessModal} onClose={() => setBusinessModal(false)} />
    </div>
  );
}
