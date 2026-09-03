"use client";

import { CustomerFormModal } from "@/components/forms/customer-form-modal";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/fields";
import { PageLoading, TableLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api, apiError } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { Customer, Paginated } from "@/lib/types";
import { money } from "@/lib/utils";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ContactRound, Edit3, Mail, Phone, Plus, Search, Trash2 } from "lucide-react";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { toast } from "sonner";

export default function CustomersPage() {
  return (
    <Suspense fallback={<PageLoading />}>
      <CustomersPageContent />
    </Suspense>
  );
}

function CustomersPageContent() {
  const { businessId } = useParams<{ businessId: string }>();
  const searchParams = useSearchParams();
  const { getBusiness, can } = useBusinesses();
  const business = getBusiness(businessId);
  const canManage = can(business, "customers.manage");
  const employee = business?.my_role === "employee";
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Customer | null>(null);
  useEffect(() => { if (canManage && searchParams.get("new") === "1") setOpen(true); }, [canManage, searchParams]);
  const query = useQuery({ queryKey: ["customers", businessId, { search, page }], queryFn: async () => (await api.get<Paginated<Customer>>(`/businesses/${businessId}/customers`, { params: { search: search || undefined, page, per_page: 20 } })).data, placeholderData: keepPreviousData });
  const archive = useMutation({ mutationFn: async (id: number) => api.delete(`/businesses/${businessId}/customers/${id}`), onSuccess: async () => { await queryClient.invalidateQueries({ queryKey: ["customers", businessId] }); toast.success("Customer archived"); }, onError: (error) => toast.error("Could not archive customer", { description: apiError(error) }) });
  if (!business) return <PageLoading />;
  if (query.isError) return <ErrorState onRetry={() => void query.refetch()} />;
  const rows = query.data?.data ?? [];
  return (
    <div className="space-y-7">
      <PageHeader eyebrow={business.name} title="Customers" description={employee ? "Use your company customer list for billing. Outstanding amounts shown here are from your own invoices only." : "Keep billing details and outstanding balances easy to find during a sale."} actions={canManage ? <Button leftIcon={<Plus size={17} />} onClick={() => setOpen(true)}>Add customer</Button> : undefined} />
      <Card className="overflow-hidden">
        <div className="border-b border-[var(--line)] p-4"><label className="relative block max-w-xl"><span className="sr-only">Search customers</span><Search size={17} className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--ink-soft)]" /><Input className="pl-10" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder="Search name, phone or email" /></label></div>
        {query.isLoading ? <TableLoading /> : rows.length ? <>
          <div className="hidden overflow-x-auto md:block"><table className="w-full min-w-[760px] text-left text-sm"><thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Customer</th><th className="px-4 py-3 font-bold">Contact</th><th className="px-4 py-3 font-bold">PAN</th><th className="px-4 py-3 text-right font-bold">Outstanding</th><th className="px-5 py-3 text-right font-bold">Actions</th></tr></thead><tbody className="divide-y divide-[var(--line)]">{rows.map((customer) => <tr key={customer.id} className="hover:bg-[var(--surface-soft)]"><td className="px-5 py-3.5"><div className="flex items-center gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-[var(--brand-soft)] text-sm font-black text-[var(--brand)]">{customer.name.slice(0, 2).toUpperCase()}</span><div><p className="font-bold">{customer.name}</p><p className="mt-0.5 max-w-[240px] truncate text-xs text-[var(--ink-soft)]">{customer.address || "No address"}</p></div></div></td><td className="px-4 py-3.5"><p className="text-sm">{customer.phone || "—"}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{customer.email || "No email"}</p></td><td className="px-4 py-3.5 text-[var(--ink-soft)]">{customer.pan_number || "—"}</td><td className="px-4 py-3.5 text-right"><span className={customer.outstanding_balance > 0 ? "font-black text-[var(--danger)]" : "font-bold"}>{money(customer.outstanding_balance, business.currency)}</span></td><td className="px-5 py-3.5"><div className="flex justify-end gap-1">{canManage ? <><button type="button" onClick={() => setEditing(customer)} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--brand-soft)] hover:text-[var(--brand)]" aria-label="Edit customer"><Edit3 size={15} /></button><button type="button" onClick={() => { if (window.confirm("Archive this customer? Existing invoices remain unchanged.")) archive.mutate(customer.id); }} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--danger-soft)] hover:text-[var(--danger)]" aria-label="Archive customer"><Trash2 size={15} /></button></> : null}</div></td></tr>)}</tbody></table></div>
          <div className="divide-y divide-[var(--line)] md:hidden">{rows.map((customer) => <button type="button" key={customer.id} disabled={!canManage} onClick={() => setEditing(customer)} className="w-full p-4 text-left enabled:hover:bg-[var(--surface-soft)]"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="truncate font-bold">{customer.name}</p><p className="mt-1 flex items-center gap-1.5 text-xs text-[var(--ink-soft)]"><Phone size={12} />{customer.phone || "No phone"}</p><p className="mt-1 flex items-center gap-1.5 truncate text-xs text-[var(--ink-soft)]"><Mail size={12} />{customer.email || "No email"}</p></div><Badge tone={customer.outstanding_balance > 0 ? "warning" : "success"}>{customer.outstanding_balance > 0 ? "Balance due" : "Clear"}</Badge></div><p className="mt-3 text-lg font-black">{money(customer.outstanding_balance, business.currency)}</p></button>)}</div>
          <Pager current={query.data?.meta.current_page ?? 1} last={query.data?.meta.last_page ?? 1} onChange={setPage} />
        </> : <EmptyState icon={ContactRound} title={search ? "No matching customers" : "No customers yet"} description={search ? "Try another name, number or email address." : "Customers are optional for walk-in sales, but saved details make repeat billing faster."} action={canManage ? <Button leftIcon={<Plus size={16} />} onClick={() => setOpen(true)}>Add customer</Button> : undefined} />}
      </Card>
      <CustomerFormModal businessId={businessId} open={canManage && (open || Boolean(editing))} customer={editing} onClose={() => { setOpen(false); setEditing(null); }} />
    </div>
  );
}
function Pager({ current, last, onChange }: { current: number; last: number; onChange: (page: number) => void }) { if (last <= 1) return null; return <div className="flex items-center justify-between border-t border-[var(--line)] p-3"><Button size="sm" variant="secondary" disabled={current <= 1} onClick={() => onChange(current - 1)}>Previous</Button><span className="text-xs text-[var(--ink-soft)]">Page {current} of {last}</span><Button size="sm" variant="secondary" disabled={current >= last} onClick={() => onChange(current + 1)}>Next</Button></div>; }
