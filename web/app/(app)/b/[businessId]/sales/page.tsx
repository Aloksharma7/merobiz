"use client";

import { InvoiceDetailModal } from "@/components/forms/invoice-detail-modal";
import { SaleFormModal } from "@/components/forms/sale-form-modal";
import { Badge, statusTone } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input, Select } from "@/components/ui/fields";
import { PageLoading, TableLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { Customer, Invoice, Paginated, Product } from "@/lib/types";
import { money, prettyDate } from "@/lib/utils";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Banknote, FileClock, HandCoins, Plus, ReceiptText, Search, ShoppingBag } from "lucide-react";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useMemo, useState } from "react";

export default function SalesPage() {
  return <Suspense fallback={<PageLoading />}><SalesPageContent /></Suspense>;
}

function SalesPageContent() {
  const { businessId } = useParams<{ businessId: string }>();
  const searchParams = useSearchParams();
  const { getBusiness, can } = useBusinesses();
  const business = getBusiness(businessId);
  const canCreate = can(business, "sales.create") || can(business, "sales.manage");
  const canSeeAll = can(business, "sales.manage");
  const employee = business?.my_role === "employee";
  const [newSale, setNewSale] = useState(false);
  const [selected, setSelected] = useState<Invoice | null>(null);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);

  useEffect(() => { if (canCreate && searchParams.get("new") === "1") setNewSale(true); }, [canCreate, searchParams]);

  const invoices = useQuery({
    queryKey: ["invoices", businessId, { search, status, page }],
    queryFn: async () => (await api.get<Paginated<Invoice>>(`/businesses/${businessId}/invoices`, { params: { search: search || undefined, status, page, per_page: 20 } })).data,
    placeholderData: keepPreviousData,
  });
  const customers = useQuery({ queryKey: ["customers", businessId, "sale-options"], queryFn: async () => (await api.get<Paginated<Customer>>(`/businesses/${businessId}/customers`, { params: { active: 1, per_page: 100 } })).data.data });
  const products = useQuery({ queryKey: ["products", businessId, "sale-options"], queryFn: async () => (await api.get<Paginated<Product>>(`/businesses/${businessId}/products`, { params: { active: 1, per_page: 100 } })).data.data });

  const rows = invoices.data?.data ?? [];
  const pageStats = useMemo(() => rows.reduce((summary, invoice) => ({
    total: summary.total + invoice.total_amount,
    collected: summary.collected + invoice.paid_amount,
    balance: summary.balance + invoice.balance_amount,
  }), { total: 0, collected: 0, balance: 0 }), [rows]);

  if (!business) return <PageLoading />;
  if (invoices.isError) return <ErrorState onRetry={() => void invoices.refetch()} />;

  return (
    <div className="space-y-7">
      <PageHeader eyebrow={business.name} title={employee ? "My sales & invoices" : "Sales & invoices"} description={canSeeAll ? "See company sales as they are entered, manage invoices and follow customer payments." : "Only sales created by you appear here. Add sales, create bills and follow payments for your own invoices."} actions={canCreate ? <Button leftIcon={<Plus size={17} />} onClick={() => setNewSale(true)}>New sale</Button> : undefined} />

      <section className="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <MiniStat label={employee ? "My invoices shown" : "Invoices shown"} value={String(rows.length)} icon={ReceiptText} />
        <MiniStat label={employee ? "My invoiced" : "Invoiced"} value={money(pageStats.total, business.currency)} icon={ShoppingBag} />
        <MiniStat label={employee ? "My collected" : "Collected"} value={money(pageStats.collected, business.currency)} icon={Banknote} />
        <MiniStat label={employee ? "My outstanding" : "Outstanding"} value={money(pageStats.balance, business.currency)} icon={HandCoins} />
      </section>

      <Card className="overflow-hidden">
        <div className="flex flex-col gap-3 border-b border-[var(--line)] p-4 lg:flex-row lg:items-center">
          <label className="relative min-w-0 flex-1"><span className="sr-only">Search invoices</span><Search size={17} className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--ink-soft)]" /><Input value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} className="pl-10" placeholder="Search invoice or customer" /></label>
          <Select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }} className="lg:w-44"><option value="all">All invoice statuses</option><option value="draft">Draft</option><option value="issued">Issued</option><option value="partial">Partially paid</option><option value="paid">Paid</option><option value="overdue">Overdue</option><option value="cancelled">Cancelled</option></Select>
        </div>

        {invoices.isLoading ? <TableLoading /> : rows.length ? (
          <>
            <div className="hidden overflow-x-auto md:block">
              <table className="w-full min-w-[860px] text-left text-sm">
                <thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Invoice</th><th className="px-4 py-3 font-bold">Customer</th>{!employee ? <th className="px-4 py-3 font-bold">Seller</th> : null}<th className="px-4 py-3 font-bold">Status</th><th className="px-4 py-3 text-right font-bold">Total</th><th className="px-4 py-3 text-right font-bold">Balance</th><th className="px-5 py-3 text-right font-bold">Action</th></tr></thead>
                <tbody className="divide-y divide-[var(--line)]">{rows.map((invoice) => <tr key={invoice.id} className="transition hover:bg-[var(--surface-soft)]"><td className="px-5 py-3.5"><p className="font-extrabold">{invoice.invoice_number}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{prettyDate(invoice.invoice_date)}</p></td><td className="px-4 py-3.5"><p className="max-w-[200px] truncate font-semibold">{invoice.customer_name || invoice.customer?.name || "Walk-in customer"}</p></td>{!employee ? <td className="px-4 py-3.5 text-[var(--ink-soft)]">{invoice.creator?.name ?? "—"}</td> : null}<td className="px-4 py-3.5"><Badge tone={statusTone(invoice.status)}>{invoice.status}</Badge></td><td className="px-4 py-3.5 text-right font-black">{money(invoice.total_amount, business.currency)}</td><td className="px-4 py-3.5 text-right font-semibold text-[var(--ink-soft)]">{money(invoice.balance_amount, business.currency)}</td><td className="px-5 py-3.5 text-right"><Button size="sm" variant="secondary" onClick={() => setSelected(invoice)}>Open</Button></td></tr>)}</tbody>
              </table>
            </div>
            <div className="divide-y divide-[var(--line)] md:hidden">{rows.map((invoice) => <button type="button" key={invoice.id} onClick={() => setSelected(invoice)} className="w-full p-4 text-left hover:bg-[var(--surface-soft)]"><div className="flex items-start justify-between gap-3"><div><p className="font-extrabold">{invoice.invoice_number}</p><p className="mt-1 text-xs text-[var(--ink-soft)]">{prettyDate(invoice.invoice_date)} · {invoice.customer_name || invoice.customer?.name || "Walk-in"}</p></div><Badge tone={statusTone(invoice.status)}>{invoice.status}</Badge></div><div className="mt-3 flex items-end justify-between gap-4"><p className="text-xs font-bold text-[var(--ink-soft)]">Balance {money(invoice.balance_amount, business.currency)}</p><p className="text-lg font-black">{money(invoice.total_amount, business.currency)}</p></div></button>)}</div>
            <Pagination current={invoices.data?.meta.current_page ?? 1} last={invoices.data?.meta.last_page ?? 1} onChange={setPage} />
          </>
        ) : <EmptyState icon={search || status !== "all" ? FileClock : ReceiptText} title={search || status !== "all" ? "No matching invoices" : "Create your first invoice"} description="Change the filters or create a new sale." action={canCreate ? <Button leftIcon={<Plus size={16} />} onClick={() => setNewSale(true)}>New sale</Button> : undefined} />}
      </Card>

      <SaleFormModal businessId={businessId} open={canCreate && newSale} onClose={() => setNewSale(false)} customers={customers.data ?? []} products={products.data ?? []} currency={business.currency} defaultTax={business.default_tax_rate} canManageCosts={can(business, "products.manage")} showBuyerPan={business.settings?.invoice?.show_customer_pan ?? false} onCreated={(invoice) => setSelected(invoice)} />
      <InvoiceDetailModal business={business} invoice={selected} open={Boolean(selected)} onClose={() => setSelected(null)} />
    </div>
  );
}

function MiniStat({ label, value, icon: Icon }: { label: string; value: string; icon: typeof ReceiptText }) {
  return <Card className="p-4"><div className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.11em] text-[var(--ink-soft)]"><Icon size={14} className="text-[var(--brand)]" />{label}</div><p className="mt-2 truncate text-xl font-black tracking-[-0.035em]">{value}</p></Card>;
}

function Pagination({ current, last, onChange }: { current: number; last: number; onChange: (page: number) => void }) {
  if (last <= 1) return null;
  return <div className="flex items-center justify-between border-t border-[var(--line)] px-4 py-3"><Button size="sm" variant="secondary" disabled={current <= 1} onClick={() => onChange(current - 1)}>Previous</Button><span className="text-xs font-semibold text-[var(--ink-soft)]">Page {current} of {last}</span><Button size="sm" variant="secondary" disabled={current >= last} onClick={() => onChange(current + 1)}>Next</Button></div>;
}
