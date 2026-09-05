"use client";

import { DateRangeControl, defaultRange, type DateRangeValue } from "@/components/dashboard/date-range-control";
import { MetricCard } from "@/components/dashboard/metric-card";
import { PersonalNavTabs } from "@/components/personal/personal-nav-tabs";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { PageLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api } from "@/lib/api";
import type { PersonalOverview } from "@/lib/types";
import { money, prettyDate } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { Banknote, Building2, CircleDollarSign, HandCoins, ReceiptText, Wallet2 } from "lucide-react";
import { useState } from "react";

export default function PersonalOverviewPage() {
  const [range, setRange] = useState<DateRangeValue>(defaultRange);
  const query = useQuery({
    queryKey: ["personal-overview", range],
    queryFn: async () => (await api.get<PersonalOverview>("/personal/overview", { params: range })).data,
  });

  if (query.isLoading) return <PageLoading />;
  if (query.isError || !query.data) return <ErrorState onRetry={() => void query.refetch()} />;
  const data = query.data;
  const currency = data.reporting_currency;

  return (
    <div className="space-y-7">
      <PageHeader eyebrow="Your finances" title="Personal" description="What you've drawn from your businesses, other income, and your own spending — kept separate from any business's books." />
      <PersonalNavTabs />
      <DateRangeControl value={range} onChange={setRange} showLast30Days={false} />

      <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricCard label="Profit taken" value={data.summary.business_profit_received} currency={currency} icon={Building2} hint="What you've logged as taken out" />
        <MetricCard label="Other income" value={data.summary.other_income} currency={currency} icon={Banknote} hint="From your income sources" />
        <MetricCard label="Personal expenses" value={data.summary.total_expenses} currency={currency} icon={ReceiptText} hint="Your own spending" />
        <MetricCard emphasis label="Net balance" value={data.summary.net_balance} currency={currency} icon={CircleDollarSign} hint="Income minus expenses, this period" />
      </section>

      <section className="grid gap-4 xl:grid-cols-2">
        <Card className="overflow-hidden">
          <CardHeader title="Profit taken" description="What you've personally logged as profit taken out, across all your businesses" />
          <CardBody className="p-0">
            {data.recent_profit.length ? <div className="divide-y divide-[var(--line)]">{data.recent_profit.map((row) => <div key={row.id} className="flex items-center gap-3 px-5 py-3.5"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]"><Building2 size={16} /></span><div className="min-w-0 flex-1"><p className="truncate text-sm font-bold">{row.business_name ?? "—"}</p><p className="text-xs text-[var(--ink-soft)]">{prettyDate(row.withdrawn_on)}</p></div><p className="text-sm font-black">{money(row.amount, row.currency ?? currency)}</p></div>)}</div> : <EmptyState icon={Building2} title="No profit taken yet" description="Log a profit withdrawal from your profile and it will show up here." />}
          </CardBody>
        </Card>

        <Card className="overflow-hidden">
          <CardHeader title="Other income" description="Entries logged against your income sources" />
          <CardBody className="p-0">
            {data.recent_income.length ? <div className="divide-y divide-[var(--line)]">{data.recent_income.map((row) => <div key={row.id} className="flex items-center gap-3 px-5 py-3.5"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]"><Wallet2 size={16} /></span><div className="min-w-0 flex-1"><p className="truncate text-sm font-bold">{row.source_name ?? "—"}</p><p className="text-xs text-[var(--ink-soft)]">{prettyDate(row.entry_date)}</p></div><p className="text-sm font-black">{money(row.amount, currency)}</p></div>)}</div> : <EmptyState icon={Wallet2} title="No other income logged" description="Add a bank or income source, then log entries against it." />}
          </CardBody>
        </Card>
      </section>

      <Card className="overflow-hidden">
        <CardHeader title="Recent personal expenses" description="Your own spending, tracked separately from any business" />
        <CardBody className="p-0">
          {data.recent_expenses.length ? <div className="divide-y divide-[var(--line)]">{data.recent_expenses.map((row) => <div key={row.id} className="flex items-center gap-3 px-5 py-3.5"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--danger-soft)] text-[var(--danger)]"><HandCoins size={16} /></span><div className="min-w-0 flex-1"><p className="truncate text-sm font-bold">{row.category}</p><p className="text-xs text-[var(--ink-soft)]">{prettyDate(row.expense_date)}{row.vendor ? ` · ${row.vendor}` : ""}</p></div><p className="text-sm font-black">{money(row.amount, currency)}</p></div>)}</div> : <EmptyState icon={ReceiptText} title="No expenses recorded" description="Track your own spending here, separate from any business's expenses." />}
        </CardBody>
      </Card>
    </div>
  );
}
