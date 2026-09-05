"use client";

import { PersonalExpenseFormModal } from "@/components/forms/personal-expense-form-modal";
import { PersonalNavTabs } from "@/components/personal/personal-nav-tabs";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/fields";
import { TableLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api, apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import type { Paginated, PersonalExpense } from "@/lib/types";
import { humanize, money, prettyDate } from "@/lib/utils";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Edit3, Plus, Search, Trash2, WalletCards } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

export default function PersonalExpensesPage() {
  const { user } = useAuth();
  const currency = user?.preferred_currency || "NPR";
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [formTarget, setFormTarget] = useState<"closed" | "new" | PersonalExpense>("closed");

  const query = useQuery({
    queryKey: ["personal-expenses", { search, page }],
    queryFn: async () => (await api.get<Paginated<PersonalExpense>>("/personal/expenses", { params: { search: search || undefined, page, per_page: 20 } })).data,
    placeholderData: keepPreviousData,
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => api.delete(`/personal/expenses/${id}`),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["personal-expenses"] }),
        queryClient.invalidateQueries({ queryKey: ["personal-overview"] }),
      ]);
      toast.success("Expense deleted");
    },
    onError: (error) => toast.error("Could not delete expense", { description: apiError(error) }),
  });

  const rows = query.data?.data ?? [];
  const total = rows.reduce((sum, row) => sum + row.amount, 0);
  if (query.isError) return <ErrorState onRetry={() => void query.refetch()} />;

  return (
    <div className="space-y-7">
      <PageHeader eyebrow="Your finances" title="Expenses" description="Your own spending, tracked separately from any business's books." actions={<Button leftIcon={<Plus size={17} />} onClick={() => setFormTarget("new")}>Add expense</Button>} />
      <PersonalNavTabs />

      <Card className="overflow-hidden">
        <div className="flex flex-col gap-3 border-b border-[var(--line)] p-4 sm:flex-row sm:items-center sm:justify-between">
          <label className="relative min-w-0 flex-1 sm:max-w-xs"><span className="sr-only">Search expenses</span><Search size={17} className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--ink-soft)]" /><Input className="pl-10" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder="Search category or vendor" /></label>
          <p className="text-sm text-[var(--ink-soft)]">Shown total <strong className="text-[var(--ink)]">{money(total, currency)}</strong></p>
        </div>
        {query.isLoading ? <TableLoading /> : rows.length ? (
          <>
            <div className="hidden overflow-x-auto md:block">
              <table className="w-full min-w-[720px] text-left text-sm">
                <thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Date & category</th><th className="px-4 py-3 font-bold">Vendor</th><th className="px-4 py-3 font-bold">Method</th><th className="px-4 py-3 text-right font-bold">Amount</th><th className="px-5 py-3 text-right font-bold">Actions</th></tr></thead>
                <tbody className="divide-y divide-[var(--line)]">{rows.map((expense) => <tr key={expense.id} className="hover:bg-[var(--surface-soft)]"><td className="px-5 py-3.5"><p className="font-bold">{expense.category}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{prettyDate(expense.expense_date)}</p></td><td className="px-4 py-3.5 font-medium">{expense.vendor || "—"}</td><td className="px-4 py-3.5 text-[var(--ink-soft)]">{humanize(expense.payment_method)}</td><td className="px-4 py-3.5 text-right font-black">{money(expense.amount, currency)}</td><td className="px-5 py-3.5"><div className="flex justify-end gap-1"><button type="button" onClick={() => setFormTarget(expense)} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--brand-soft)] hover:text-[var(--brand)]" aria-label="Edit expense"><Edit3 size={15} /></button><button type="button" onClick={() => { if (window.confirm("Delete this expense record?")) deleteMutation.mutate(expense.id); }} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--danger-soft)] hover:text-[var(--danger)]" aria-label="Delete expense"><Trash2 size={15} /></button></div></td></tr>)}</tbody>
              </table>
            </div>
            <div className="divide-y divide-[var(--line)] md:hidden">{rows.map((expense) => <button type="button" key={expense.id} onClick={() => setFormTarget(expense)} className="w-full p-4 text-left hover:bg-[var(--surface-soft)]"><div className="flex items-start justify-between gap-3"><div><p className="font-bold">{expense.category}</p><p className="mt-1 text-xs text-[var(--ink-soft)]">{prettyDate(expense.expense_date)} · {expense.vendor || "No vendor"}</p></div><p className="text-lg font-black">{money(expense.amount, currency)}</p></div></button>)}</div>
            <Pager current={query.data?.meta.current_page ?? 1} last={query.data?.meta.last_page ?? 1} onChange={setPage} />
          </>
        ) : <EmptyState icon={WalletCards} title={search ? "No matching expenses" : "No expenses recorded"} description={search ? "Change the search to see other records." : "Track your own spending here, separate from any business."} action={<Button leftIcon={<Plus size={16} />} onClick={() => setFormTarget("new")}>Add expense</Button>} />}
      </Card>

      <PersonalExpenseFormModal
        open={formTarget !== "closed"}
        onClose={() => setFormTarget("closed")}
        expense={formTarget === "closed" || formTarget === "new" ? null : formTarget}
      />
    </div>
  );
}

function Pager({ current, last, onChange }: { current: number; last: number; onChange: (page: number) => void }) {
  if (last <= 1) return null;
  return <div className="flex items-center justify-between border-t border-[var(--line)] p-3"><Button size="sm" variant="secondary" disabled={current <= 1} onClick={() => onChange(current - 1)}>Previous</Button><span className="text-xs text-[var(--ink-soft)]">{current} / {last}</span><Button size="sm" variant="secondary" disabled={current >= last} onClick={() => onChange(current + 1)}>Next</Button></div>;
}
