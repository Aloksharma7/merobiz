"use client";

import { BusinessMark } from "@/components/business-mark";
import { DateRangeControl, defaultRange, type DateRangeValue } from "@/components/dashboard/date-range-control";
import { MetricCard } from "@/components/dashboard/metric-card";
import { MyProfileCard } from "@/components/dashboard/my-profile-card";
import { TrendChart } from "@/components/dashboard/trend-chart";
import { ProfitWithdrawalFormModal } from "@/components/forms/profit-withdrawal-form-modal";
import { Badge, statusTone } from "@/components/ui/badge";
import { LinkButton } from "@/components/ui/button";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { PageLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import { brandingFor } from "@/lib/branding";
import { useBusinesses } from "@/lib/business-context";
import type { BusinessDashboard, MySalary } from "@/lib/types";
import { cn, humanize, money, number, prettyDate } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { ArrowRight, Boxes, CalendarClock, CircleDollarSign, ContactRound, HandCoins, Landmark, Plus, ReceiptText, TrendingUp, Wallet2, WalletCards } from "lucide-react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { useState } from "react";

export default function BusinessDashboardPage() {
  const { businessId } = useParams<{ businessId: string }>();
  const { getBusiness } = useBusinesses();
  const { user } = useAuth();
  const business = getBusiness(businessId);
  const [range, setRange] = useState<DateRangeValue>(defaultRange);
  const [withdrawalOpen, setWithdrawalOpen] = useState(false);
  const query = useQuery({
    queryKey: ["business-dashboard", businessId, range],
    queryFn: async () => (await api.get<BusinessDashboard>(`/businesses/${businessId}/dashboard`, { params: range })).data,
  });
  const mySalaryQuery = useQuery({
    queryKey: ["my-salary", businessId],
    queryFn: async () => (await api.get<MySalary>(`/businesses/${businessId}/my-salary`)).data,
    enabled: business?.my_role === "employee",
  });

  if (query.isLoading || !business) return <PageLoading />;
  if (query.isError || !query.data) return <ErrorState onRetry={() => void query.refetch()} />;
  const data = query.data;
  const currency = data.business.currency;
  const financial = data.mode === "financial";
  const owner = data.business.my_role === "owner";
  const admin = data.business.my_role === "admin";
  const employee = data.business.my_role === "employee";
  const branding = brandingFor(business);
  const layout = data.business.dashboard_settings;

  const quickActions = [
    data.permissions.can_manage_sales && { label: "New sale", description: "Create an invoice", href: `/b/${businessId}/sales?new=1`, icon: ReceiptText },
    data.permissions.can_manage_expenses && { label: "Add expense", description: "Record a business cost", href: `/b/${businessId}/expenses?new=1`, icon: WalletCards },
    data.permissions.can_manage_customers && { label: "Add customer", description: "Save contact details", href: `/b/${businessId}/customers?new=1`, icon: ContactRound },
    data.permissions.can_manage_products && { label: "Add item", description: "Product or service", href: `/b/${businessId}/catalog?new=1`, icon: Boxes },
  ].filter(Boolean) as Array<{ label: string; description: string; href: string; icon: typeof ReceiptText }>;

  return (
    <div className="space-y-7">
      <PageHeader
        eyebrow={employee ? <span className="flex items-center gap-2"><BusinessMark business={business} compact />{branding.tagline || "My sales"}</span> : <span className="flex items-center gap-2"><span className="grid h-6 min-w-6 place-items-center rounded-md bg-[var(--brand-soft)] px-1.5 text-[10px] font-black text-[var(--ink)]">{data.business.code}</span>{humanize(data.business.business_type)}</span>}
        title={data.business.name}
        description={owner ? `${data.business.profit_share_percent}% of finalized net profit is attributable to your ownership agreement.` : admin ? "Full business performance with staff sales and operational controls." : `Your sales, invoices, collections and customer work for ${data.business.name}. Only your own sales activity is included here.`}
        actions={data.permissions.can_manage_sales ? <LinkButton href={`/b/${businessId}/sales?new=1`} leftIcon={<Plus size={17} />}>New sale</LinkButton> : undefined}
      />

      <section className={cn("grid gap-4 sm:grid-cols-2", owner ? "xl:grid-cols-3" : "xl:grid-cols-2")}>
        <MyProfileCard
          name={user?.name ?? ""}
          email={user?.email}
          initials={user?.initials ?? ""}
          roleLabel={`${humanize(data.business.my_role)} · ${business.name}`}
          ownershipPercent={owner ? data.business.ownership_percent : null}
          profitSharePercent={owner ? data.business.profit_share_percent : null}
          onLogProfit={owner ? () => setWithdrawalOpen(true) : undefined}
        />
        <MetricCard emphasis={!owner} label={financial ? "This month's expected profit" : "This month's sales"} value={financial ? data.month_to_date.profit : data.month_to_date.sales} currency={currency} icon={CalendarClock} hint="Month to date" />
        {owner ? <MetricCard emphasis label="Profit taken" value={data.profit_collected_this_month} currency={currency} icon={Wallet2} hint="Logged this month" /> : null}
      </section>
      <ProfitWithdrawalFormModal open={withdrawalOpen} onClose={() => setWithdrawalOpen(false)} businesses={[{ id: Number(businessId), name: business.name }]} />

      <DateRangeControl value={range} onChange={setRange} />

      {layout.show_overview_cards ? (
        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <MetricCard emphasis={financial} label={owner ? "Your expected profit" : admin ? "Business expected profit" : "My sales"} value={owner ? data.summary.attributable_profit ?? 0 : admin ? data.summary.net_profit : data.summary.net_sales} currency={currency} icon={CircleDollarSign} hint={owner ? `${data.business.profit_share_percent}% of live net profit, not yet withdrawn` : admin ? "Sales after costs and expenses" : "Only invoices created by you"} />
          <MetricCard label={employee ? "My invoices" : "Net sales"} value={employee ? data.summary.invoice_count : data.summary.net_sales} currency={currency} valueFormatter={employee ? (value) => number(value) : undefined} icon={TrendingUp} change={employee ? undefined : data.summary.net_sales_change} hint={employee ? `${money(data.summary.net_sales, currency)} total sales` : `${data.summary.invoice_count} invoices`} />
          <MetricCard label={employee ? "My collections" : "Cash collected"} value={data.summary.cash_collected} currency={currency} icon={Landmark} hint={employee ? "Payments collected against your invoices" : "Payments received during this period"} />
          <MetricCard label={employee ? "My outstanding" : "Receivables"} value={data.summary.receivables} currency={currency} icon={HandCoins} hint={employee ? "Unpaid balance on your invoices" : "Balance on invoices dated in this range"} />
        </section>
      ) : null}

      {financial && layout.show_profit_breakdown ? (
        <section className="grid gap-3 rounded-[var(--radius)] border border-[var(--line)] bg-white p-3 shadow-[var(--shadow-sm)] sm:grid-cols-2 xl:grid-cols-4">
          {[
            ["Expected gross profit", data.summary.gross_profit, "Sales minus direct cost"],
            ["Approved expenses", data.summary.expenses, "Operating costs"],
            ["Payroll paid", data.summary.payroll_cost, "Actual salary, commission and advance payouts"],
            ["Expected net profit", data.summary.net_profit, "Before owner distribution, not yet withdrawn"],
          ].map(([label, value, note]) => <div key={String(label)} className="rounded-2xl bg-[var(--surface-soft)] px-4 py-3.5"><p className="text-[10px] font-bold uppercase tracking-[0.12em] text-[var(--ink-soft)]">{String(label)}</p><p className="mt-1.5 text-lg font-black tracking-[-0.03em]">{money(Number(value), currency)}</p><p className="mt-1 text-[11px] text-[var(--ink-soft)]">{String(note)}</p></div>)}
          <div className="rounded-2xl bg-[var(--surface-soft)] px-4 py-3.5 sm:col-span-2 xl:col-span-4"><p className="text-[10px] font-bold uppercase tracking-[0.12em] text-[var(--ink-soft)]">Commission accrued (estimated)</p><p className="mt-1.5 text-lg font-black tracking-[-0.03em]">{money(data.summary.commissions, currency)}</p><p className="mt-1 text-[11px] text-[var(--ink-soft)]">What commission-based staff are estimated to be owed from their sales so far — this only reduces profit once actually paid out from their Payroll page, alongside everyone else's payments.</p></div>
        </section>
      ) : null}

      {layout.show_quick_actions && quickActions.length ? (
        <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {quickActions.map(({ icon: Icon, ...action }) => <Link href={action.href} key={action.label} className="group flex min-h-24 items-center gap-4 rounded-[var(--radius)] border border-[var(--line)] bg-white p-4 shadow-[var(--shadow-sm)] transition hover:border-[#b3c3b6] hover:shadow-[0_12px_30px_rgb(17_48_35/0.07)]"><span className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[var(--brand-soft)] text-[var(--brand)]"><Icon size={20} /></span><span className="min-w-0 flex-1"><span className="block font-bold">{action.label}</span><span className="mt-0.5 block truncate text-xs text-[var(--ink-soft)]">{action.description}</span></span><ArrowRight size={17} className="text-[var(--ink-soft)] transition group-hover:translate-x-1 group-hover:text-[var(--brand)]" /></Link>)}
        </section>
      ) : null}

      {layout.show_performance_trend || layout.show_top_products ? (
        <section className={cn("grid min-w-0 gap-4", layout.show_performance_trend && layout.show_top_products && "xl:grid-cols-[minmax(0,1.65fr)_minmax(330px,0.85fr)]")}>
          {layout.show_performance_trend ? <TrendChart data={data.trend} currency={currency} salesOnly={!financial} /> : null}
          {layout.show_top_products ? (
            <Card className="overflow-hidden">
              <CardHeader title={employee ? "My top products & services" : "Top products & services"} description={employee ? "Based only on your sales" : "By net item sales"} />
              <CardBody className="p-0">
                {data.top_products.length ? <div className="divide-y divide-[var(--line)]">{data.top_products.map((product, index) => <div key={`${product.name}-${index}`} className="flex items-center gap-3 px-5 py-3.5"><span className="grid h-9 w-9 place-items-center rounded-xl bg-[var(--surface-soft)] text-xs font-black text-[var(--brand)]">{index + 1}</span><div className="min-w-0 flex-1"><p className="truncate text-sm font-bold">{product.name}</p><p className="text-xs text-[var(--ink-soft)]">{number(product.quantity, product.quantity % 1 ? 2 : 0)} sold</p></div><p className="text-sm font-black">{money(product.sales, currency)}</p></div>)}</div> : <EmptyState icon={Boxes} title="No item sales yet" description="Your best-performing products and services will appear here." />}
              </CardBody>
            </Card>
          ) : null}
        </section>
      ) : null}

      {layout.show_recent_invoices || layout.show_expense_mix ? (
        <section className={cn("grid gap-4", layout.show_recent_invoices && layout.show_expense_mix && "xl:grid-cols-[minmax(0,1.3fr)_minmax(300px,0.7fr)]")}>
          {layout.show_recent_invoices ? (
            <Card className="overflow-hidden">
              <CardHeader title={employee ? "My recent invoices" : "Recent invoices"} description={employee ? "Only invoices created by you" : "Latest sales activity"} action={<Link href={`/b/${businessId}/sales`} className="text-xs font-bold text-[var(--brand)] hover:underline">View all</Link>} />
              {data.recent_invoices.length ? (
                <>
                  <div className="hidden overflow-x-auto md:block">
                    <table className="w-full min-w-[680px] text-left text-sm">
                      <thead><tr className="border-b border-[var(--line)] bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Invoice</th><th className="px-4 py-3 font-bold">Customer</th>{!employee ? <th className="px-4 py-3 font-bold">Seller</th> : null}<th className="px-4 py-3 font-bold">Status</th><th className="px-5 py-3 text-right font-bold">Amount</th></tr></thead>
                      <tbody className="divide-y divide-[var(--line)]">{data.recent_invoices.map((invoice) => <tr key={invoice.id} className="hover:bg-[var(--surface-soft)]"><td className="px-5 py-3.5"><p className="font-bold">{invoice.invoice_number}</p><p className="text-xs text-[var(--ink-soft)]">{prettyDate(invoice.invoice_date)}</p></td><td className="px-4 py-3.5 font-medium">{invoice.customer_name}</td>{!employee ? <td className="px-4 py-3.5 text-[var(--ink-soft)]">{invoice.seller_name}</td> : null}<td className="px-4 py-3.5"><Badge tone={statusTone(invoice.status)}>{invoice.status}</Badge></td><td className="px-5 py-3.5 text-right font-black">{money(invoice.total_amount, currency)}</td></tr>)}</tbody>
                    </table>
                  </div>
                  <div className="divide-y divide-[var(--line)] md:hidden">{data.recent_invoices.map((invoice) => <div key={invoice.id} className="p-4"><div className="flex items-start justify-between gap-3"><div><p className="font-bold">{invoice.invoice_number}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{invoice.customer_name}{!employee ? ` · ${invoice.seller_name}` : ""}</p></div><Badge tone={statusTone(invoice.status)}>{invoice.status}</Badge></div><div className="mt-3 flex items-center justify-between"><p className="text-xs text-[var(--ink-soft)]">{prettyDate(invoice.invoice_date)}</p><p className="text-lg font-black">{money(invoice.total_amount, currency)}</p></div></div>)}</div>
                </>
              ) : <EmptyState icon={ReceiptText} title="No invoices yet" description={data.permissions.can_manage_sales ? "Create the first sale to start tracking performance." : "Sales activity will appear here when invoices are created."} action={data.permissions.can_manage_sales ? <LinkButton href={`/b/${businessId}/sales?new=1`} leftIcon={<Plus size={16} />}>Create invoice</LinkButton> : undefined} />}
            </Card>
          ) : null}

          {layout.show_expense_mix ? (
            <Card className="overflow-hidden">
              <CardHeader title={financial ? "Expense mix" : "My commission"} description={financial ? "Approved costs by category" : "Commission generated by my sales"} />
              <CardBody>
                {financial && data.expense_categories.length ? <div className="space-y-4">{data.expense_categories.map((row) => { const total = data.expense_categories.reduce((sum, item) => sum + item.total, 0); const share = total ? (row.total / total) * 100 : 0; return <div key={row.category}><div className="flex items-center justify-between gap-4 text-sm"><span className="truncate font-semibold">{row.category}</span><span className="shrink-0 font-black">{money(row.total, currency)}</span></div><div className="mt-2 h-2 overflow-hidden rounded-full bg-[#edf0ed]"><div className="h-full rounded-full bg-[var(--brand)]" style={{ width: `${Math.max(3, share)}%` }} /></div></div>; })}</div> : !financial ? <div className="rounded-2xl bg-[var(--brand-deep)] p-5 text-[var(--on-brand-deep)]"><p className="text-xs font-bold uppercase tracking-[0.12em] text-[var(--on-brand-deep)]/50">My estimated commission</p><p className="mt-2 text-3xl font-black tracking-[-0.04em]">{money(data.summary.commissions, currency)}</p><p className="mt-2 text-xs leading-5 text-[var(--on-brand-deep)]/55">Final commission depends on the rules and payment status set by the business.</p></div> : <EmptyState icon={WalletCards} title="No approved expenses" description="Expense categories appear after costs are approved." />}
              </CardBody>
            </Card>
          ) : null}
        </section>
      ) : null}

      {mySalaryQuery.data?.visible || (mySalaryQuery.data && mySalaryQuery.data.outstanding_loan > 0) ? (
        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {mySalaryQuery.data.visible ? (
            <div className="rounded-[var(--radius)] border border-[var(--brand)] bg-[var(--brand-deep)] p-5 text-[var(--on-brand-deep)] sm:col-span-2">
              <p className="text-xs font-bold uppercase tracking-[0.12em] text-[var(--on-brand-deep)]/55">{mySalaryQuery.data.pay_type === "fixed_salary" ? "My salary" : "My commission"}</p>
              <p className="mt-2 text-3xl font-black tracking-[-0.04em]">{money(Math.abs(mySalaryQuery.data.pending), currency)}</p>
              <p className="mt-2 text-xs leading-5 text-[var(--on-brand-deep)]/55">
                {mySalaryQuery.data.pending < 0 ? "Overpaid — will be deducted from what's owed next" : "Pending"}
                {mySalaryQuery.data.pay_type === "fixed_salary" ? ` · ${money(mySalaryQuery.data.salary_amount, currency)}/month` : ""} · {money(mySalaryQuery.data.paid_total, currency)} paid so far
              </p>
            </div>
          ) : null}
          {mySalaryQuery.data.outstanding_loan > 0 ? (
            <div className="rounded-[var(--radius)] border border-[var(--line)] bg-[var(--surface-soft)] p-5 sm:col-span-2">
              <p className="text-xs font-bold uppercase tracking-[0.12em] text-[var(--ink-soft)]">Advance you still owe</p>
              <p className="mt-2 text-3xl font-black tracking-[-0.04em]">{money(mySalaryQuery.data.outstanding_loan, currency)}</p>
              <p className="mt-2 text-xs leading-5 text-[var(--ink-soft)]">Given as a loan, separate from your regular pay. Ask an admin if you're unsure why.</p>
            </div>
          ) : null}
          <div className="sm:col-span-2"><LinkButton href={`/b/${businessId}/my-pay`} variant="secondary" size="sm">View full pay history</LinkButton></div>
        </section>
      ) : null}
    </div>
  );
}
