"use client";

import { ExpenseFormModal } from "@/components/forms/expense-form-modal";
import { Badge, statusTone } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input, Select } from "@/components/ui/fields";
import { PageLoading, TableLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api, apiError } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { ApiMessage, Expense, ExpenseStatus, Paginated } from "@/lib/types";
import { humanize, money, prettyDate } from "@/lib/utils";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Check, Clock3, Plus, Receipt, Search, Trash2, WalletCards, X } from "lucide-react";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { toast } from "sonner";

export default function ExpensesPage() {
  return (
    <Suspense fallback={<PageLoading />}>
      <ExpensesPageContent />
    </Suspense>
  );
}

function ExpensesPageContent() {
  const { businessId } = useParams<{ businessId: string }>();
  const searchParams = useSearchParams();
  const { getBusiness, can } = useBusinesses();
  const business = getBusiness(businessId);
  const canManage = can(business, "expenses.manage");
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);
  useEffect(() => { if (canManage && searchParams.get("new") === "1") setOpen(true); }, [canManage, searchParams]);

  const query = useQuery({
    queryKey: ["expenses", businessId, { search, status, page }],
    queryFn: async () => (await api.get<Paginated<Expense>>(`/businesses/${businessId}/expenses`, { params: { search: search || undefined, status, page, per_page: 20 } })).data,
    placeholderData: keepPreviousData,
  });

  const statusMutation = useMutation({
    mutationFn: async ({ id, next }: { id: number; next: ExpenseStatus }) => (await api.patch<ApiMessage<{ expense: Expense }>>(`/businesses/${businessId}/expenses/${id}/status`, { status: next })).data,
    onSuccess: async (_, variables) => { await Promise.all([queryClient.invalidateQueries({ queryKey: ["expenses", businessId] }), queryClient.invalidateQueries({ queryKey: ["business-dashboard", businessId] })]); toast.success(variables.next === "approved" ? "Expense approved" : "Expense rejected"); },
    onError: (error) => toast.error("Could not update expense", { description: apiError(error) }),
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => api.delete(`/businesses/${businessId}/expenses/${id}`),
    onSuccess: async () => { await queryClient.invalidateQueries({ queryKey: ["expenses", businessId] }); toast.success("Expense deleted"); },
    onError: (error) => toast.error("Could not delete expense", { description: apiError(error) }),
  });

  const rows = query.data?.data ?? [];
  const summary = rows.reduce((acc, row) => ({ total: acc.total + row.amount, approved: acc.approved + (row.status === "approved" ? row.amount : 0), pending: acc.pending + (row.status === "pending" ? row.amount : 0) }), { total: 0, approved: 0, pending: 0 });
  const canApprove = can(business, "expenses.approve");
  if (!business) return <PageLoading />;
  if (query.isError) return <ErrorState onRetry={() => void query.refetch()} />;

  return (
    <div className="space-y-7">
      <PageHeader eyebrow={business.name} title="Expenses" description="Keep business costs simple to enter, easy to approve and visible in profit calculations." actions={canManage ? <Button leftIcon={<Plus size={17} />} onClick={() => setOpen(true)}>Add expense</Button> : undefined} />
      <section className="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <MiniStat label="Shown total" value={money(summary.total, business.currency)} icon={WalletCards} />
        <MiniStat label="Approved" value={money(summary.approved, business.currency)} icon={Check} />
        <MiniStat label="Pending" value={money(summary.pending, business.currency)} icon={Clock3} />
        <MiniStat label="Records" value={String(query.data?.meta.total ?? rows.length)} icon={Receipt} />
      </section>
      <Card className="overflow-hidden">
        <div className="flex flex-col gap-3 border-b border-[var(--line)] p-4 sm:flex-row"><label className="relative flex-1"><span className="sr-only">Search expenses</span><Search size={17} className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--ink-soft)]" /><Input className="pl-10" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder="Search category, vendor or reference" /></label><Select className="sm:w-44" value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }}><option value="all">All statuses</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></Select></div>
        {query.isLoading ? <TableLoading /> : rows.length ? (
          <>
            <div className="hidden overflow-x-auto md:block"><table className="w-full min-w-[850px] text-left text-sm"><thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Date & category</th><th className="px-4 py-3 font-bold">Vendor</th><th className="px-4 py-3 font-bold">Method</th><th className="px-4 py-3 font-bold">Status</th><th className="px-4 py-3 text-right font-bold">Amount</th><th className="px-5 py-3 text-right font-bold">Actions</th></tr></thead><tbody className="divide-y divide-[var(--line)]">{rows.map((expense) => <tr key={expense.id} className="hover:bg-[var(--surface-soft)]"><td className="px-5 py-3.5"><p className="font-bold">{expense.category}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{prettyDate(expense.expense_date)}{expense.reference ? ` · ${expense.reference}` : ""}</p></td><td className="px-4 py-3.5 font-medium">{expense.vendor || "—"}</td><td className="px-4 py-3.5 text-[var(--ink-soft)]">{humanize(expense.payment_method)}</td><td className="px-4 py-3.5"><Badge tone={statusTone(expense.status)}>{expense.status}</Badge></td><td className="px-4 py-3.5 text-right font-black">{money(expense.amount, business.currency)}</td><td className="px-5 py-3.5"><div className="flex justify-end gap-1">{canApprove && expense.status === "pending" ? <><Button size="sm" variant="quiet" onClick={() => statusMutation.mutate({ id: expense.id, next: "approved" })}>Approve</Button><Button size="sm" variant="ghost" onClick={() => statusMutation.mutate({ id: expense.id, next: "rejected" })}>Reject</Button></> : null}{canManage ? <button type="button" onClick={() => { if (window.confirm("Delete this expense record?")) deleteMutation.mutate(expense.id); }} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--danger-soft)] hover:text-[var(--danger)]" aria-label="Delete expense"><Trash2 size={15} /></button> : null}</div></td></tr>)}</tbody></table></div>
            <div className="divide-y divide-[var(--line)] md:hidden">{rows.map((expense) => <div key={expense.id} className="p-4"><div className="flex items-start justify-between gap-3"><div><p className="font-bold">{expense.category}</p><p className="mt-1 text-xs text-[var(--ink-soft)]">{prettyDate(expense.expense_date)} · {expense.vendor || "No vendor"}</p></div><Badge tone={statusTone(expense.status)}>{expense.status}</Badge></div><div className="mt-3 flex items-center justify-between"><p className="text-lg font-black">{money(expense.amount, business.currency)}</p><div className="flex gap-2">{canApprove && expense.status === "pending" ? <><button type="button" onClick={() => statusMutation.mutate({ id: expense.id, next: "approved" })} className="grid h-9 w-9 place-items-center rounded-lg bg-[var(--brand-soft)] text-[var(--brand)]" aria-label="Approve"><Check size={16} /></button><button type="button" onClick={() => statusMutation.mutate({ id: expense.id, next: "rejected" })} className="grid h-9 w-9 place-items-center rounded-lg bg-[var(--danger-soft)] text-[var(--danger)]" aria-label="Reject"><X size={16} /></button></> : null}{canManage ? <button type="button" onClick={() => { if (window.confirm("Delete this expense record?")) deleteMutation.mutate(expense.id); }} className="grid h-9 w-9 place-items-center rounded-lg border border-[var(--line)] text-[var(--ink-soft)] hover:bg-[var(--danger-soft)] hover:text-[var(--danger)]" aria-label="Delete expense"><Trash2 size={15} /></button> : null}</div></div></div>)}</div>
            <Pager current={query.data?.meta.current_page ?? 1} last={query.data?.meta.last_page ?? 1} onChange={setPage} />
          </>
        ) : <EmptyState icon={WalletCards} title={search || status !== "all" ? "No matching expenses" : "No expenses recorded"} description={search || status !== "all" ? "Change the filters to see other expense records." : "Record operating costs so the profit dashboard reflects the real business result."} action={canManage ? <Button leftIcon={<Plus size={16} />} onClick={() => setOpen(true)}>Add expense</Button> : undefined} />}
      </Card>
      <ExpenseFormModal businessId={businessId} open={canManage && open} onClose={() => setOpen(false)} />
    </div>
  );
}

function MiniStat({ label, value, icon: Icon }: { label: string; value: string; icon: typeof WalletCards }) { return <Card className="p-4"><div className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.11em] text-[var(--ink-soft)]"><Icon size={14} className="text-[var(--brand)]" />{label}</div><p className="mt-2 truncate text-xl font-black tracking-[-0.035em]">{value}</p></Card>; }
function Pager({ current, last, onChange }: { current: number; last: number; onChange: (page: number) => void }) { if (last <= 1) return null; return <div className="flex items-center justify-between border-t border-[var(--line)] p-3"><Button size="sm" variant="secondary" disabled={current <= 1} onClick={() => onChange(current - 1)}>Previous</Button><span className="text-xs text-[var(--ink-soft)]">{current} / {last}</span><Button size="sm" variant="secondary" disabled={current >= last} onClick={() => onChange(current + 1)}>Next</Button></div>; }
