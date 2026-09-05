"use client";

import { DateRangeControl, defaultRange, type DateRangeValue } from "@/components/dashboard/date-range-control";
import { MemberEditModal } from "@/components/forms/member-edit-modal";
import { MemberFormModal } from "@/components/forms/member-form-modal";
import { SalaryPaymentModal } from "@/components/forms/salary-payment-modal";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { PageLoading, TableLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api, apiError, downloadFile } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { Member } from "@/lib/types";
import { cn, humanize, money, number } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { Award, Download, Edit3, HandCoins, Plus, ReceiptText, TrendingUp, UsersRound, Wallet } from "lucide-react";
import { useParams } from "next/navigation";
import { useState } from "react";
import { toast } from "sonner";

function roleLabel(member: Member): string {
  if (member.is_founder) return "Founder";
  if (member.role === "owner") return member.full_control ? "Owner · full control" : "Co-owner";
  return humanize(member.role);
}

function PayStatus({ member, currency }: { member: Member; currency: string }) {
  return (
    <div>
      <Badge tone={member.pay_type === "fixed_salary" ? "info" : "neutral"}>{member.pay_type === "fixed_salary" ? "Salary" : "Commission"}</Badge>
      {member.salary_pending > 0 ? <p className="mt-1 text-xs font-bold text-[var(--danger)]">Owed {money(member.salary_pending, currency)}</p>
        : member.salary_pending < 0 ? <p className="mt-1 text-xs font-bold text-[var(--info)]">Overpaid {money(Math.abs(member.salary_pending), currency)}</p>
        : <p className="mt-1 text-xs text-[var(--ink-soft)]">Nothing owed</p>}
      {member.outstanding_loan > 0 ? <p className="mt-0.5 text-xs font-semibold text-[var(--accent)]">Loan {money(member.outstanding_loan, currency)}</p> : null}
    </div>
  );
}

export default function TeamPage() {
  const { businessId } = useParams<{ businessId: string }>();
  const { getBusiness, can } = useBusinesses();
  const business = getBusiness(businessId);
  const [range, setRange] = useState<DateRangeValue>(defaultRange);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Member | null>(null);
  const [payingSalary, setPayingSalary] = useState<Member | null>(null);
  const [exporting, setExporting] = useState(false);
  const query = useQuery({ queryKey: ["team", businessId, range], queryFn: async () => (await api.get<{ data: Member[] }>(`/businesses/${businessId}/team`, { params: range })).data.data });
  const rows = query.data ?? [];
  const canManage = can(business, "team.manage");

  async function exportCsv() {
    setExporting(true);
    try {
      await downloadFile(`/businesses/${businessId}/team/export`, { start: range.start, end: range.end }, `payroll-${businessId}-${range.start}-to-${range.end}.csv`);
    } catch (error) {
      toast.error("Could not export payroll", { description: apiError(error) });
    } finally {
      setExporting(false);
    }
  }
  const stats = { active: rows.filter((row) => row.active).length, sales: rows.reduce((sum, row) => sum + row.sales, 0), invoices: rows.reduce((sum, row) => sum + row.invoice_count, 0), commission: rows.reduce((sum, row) => sum + row.commission_earned, 0), owed: rows.reduce((sum, row) => sum + Math.max(0, row.salary_pending), 0), loans: rows.reduce((sum, row) => sum + row.outstanding_loan, 0) };
  const maxSales = Math.max(...rows.map((row) => row.sales), 1);
  if (!business) return <PageLoading />;
  if (query.isError) return <ErrorState onRetry={() => void query.refetch()} />;
  return (
    <div className="space-y-7">
      <PageHeader eyebrow={business.name} title="Team performance" description="Give each person only the access they need and compare sales without exposing partner finances." actions={canManage ? <><Button variant="secondary" leftIcon={<Download size={16} />} onClick={() => void exportCsv()} loading={exporting}>Export payroll</Button><Button leftIcon={<Plus size={17} />} onClick={() => setOpen(true)}>Add member</Button></> : undefined} />
      <DateRangeControl value={range} onChange={setRange} />
      <section className="grid grid-cols-2 gap-3 xl:grid-cols-6"><Mini label="Active members" value={String(stats.active)} icon={UsersRound} /><Mini label="Team sales" value={money(stats.sales, business.currency)} icon={TrendingUp} /><Mini label="Invoices" value={String(stats.invoices)} icon={ReceiptText} /><Mini label="Commission" value={money(stats.commission, business.currency)} icon={Award} /><Mini label="Owed to staff" value={money(stats.owed, business.currency)} icon={HandCoins} emphasis /><Mini label="Loans outstanding" value={money(stats.loans, business.currency)} icon={Wallet} /></section>
      <Card className="overflow-hidden">
        {query.isLoading ? <TableLoading /> : rows.length ? <>
          <div className="hidden overflow-x-auto md:block"><table className="w-full min-w-[960px] text-left text-sm"><thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Member</th><th className="px-4 py-3 font-bold">Role</th><th className="px-4 py-3 text-right font-bold">Sales</th><th className="px-4 py-3 text-right font-bold">Invoices</th><th className="px-4 py-3 text-right font-bold">Commission</th><th className="px-4 py-3 font-bold">Pay</th><th className="px-4 py-3 text-right font-bold">Profit share</th><th className="px-5 py-3 text-right font-bold">Action</th></tr></thead><tbody className="divide-y divide-[var(--line)]">{[...rows].sort((a, b) => b.sales - a.sales).map((member, index) => <tr key={member.id} className="hover:bg-[var(--surface-soft)]"><td className="px-5 py-3.5"><div className="flex items-center gap-3"><span className="relative grid h-10 w-10 place-items-center rounded-xl bg-[var(--brand-soft)] text-xs font-black text-[var(--brand)]">{member.initials}{index === 0 && member.sales > 0 ? <Award size={13} className="absolute -right-1 -top-1 rounded-full bg-[var(--accent)] p-0.5 text-[var(--brand-deep)]" /> : null}</span><div><p className="font-bold">{member.name}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{member.title || member.email}</p></div></div></td><td className="px-4 py-3.5"><Badge tone={member.active ? "success" : "neutral"}>{roleLabel(member)}</Badge></td><td className="px-4 py-3.5 text-right"><p className="font-black">{money(member.sales, business.currency)}</p><div className="ml-auto mt-1.5 h-1.5 w-24 overflow-hidden rounded-full bg-[#e9ede9]"><div className="h-full rounded-full bg-[var(--brand)]" style={{ width: `${Math.max(member.sales > 0 ? 6 : 0, member.sales / maxSales * 100)}%` }} /></div></td><td className="px-4 py-3.5 text-right font-bold">{member.invoice_count}</td><td className="px-4 py-3.5 text-right font-semibold">{money(member.commission_earned, business.currency)}</td><td className="px-4 py-3.5"><PayStatus member={member} currency={business.currency} /></td><td className="px-4 py-3.5 text-right text-[var(--ink-soft)]">{number(member.profit_share_percent, 1)}%</td><td className="px-5 py-3.5 text-right"><div className="flex justify-end gap-1">{canManage ? <button type="button" onClick={() => setPayingSalary(member)} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--brand-soft)] hover:text-[var(--brand)]" aria-label="View payroll and payment history"><Wallet size={15} /></button> : null}{canManage ? <button type="button" onClick={() => setEditing(member)} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--brand-soft)] hover:text-[var(--brand)]" aria-label="Edit member"><Edit3 size={15} /></button> : null}</div></td></tr>)}</tbody></table></div>
          <div className="grid gap-3 p-4 sm:grid-cols-2 md:hidden">{[...rows].sort((a, b) => b.sales - a.sales).map((member) => <div key={member.id} className="rounded-2xl border border-[var(--line)] p-4"><div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-[var(--brand-soft)] text-xs font-black text-[var(--brand)]">{member.initials}</span><div className="min-w-0 flex-1"><p className="truncate font-bold">{member.name}</p><p className="mt-0.5 truncate text-xs text-[var(--ink-soft)]">{roleLabel(member)} · {member.invoice_count} invoices</p></div><Badge tone={member.active ? "success" : "neutral"}>{member.active ? "Active" : "Off"}</Badge></div><div className="mt-4 flex items-end justify-between"><p className="text-xs text-[var(--ink-soft)]">{member.salary_pending < 0 ? "Overpaid" : member.pay_type === "fixed_salary" ? "Salary owed" : "Commission owed"}<br /><strong className={member.salary_pending > 0 ? "text-[var(--danger)]" : member.salary_pending < 0 ? "text-[var(--info)]" : "text-[var(--ink)]"}>{money(Math.abs(member.salary_pending), business.currency)}</strong>{member.outstanding_loan > 0 ? <span className="block text-[11px] font-semibold text-[var(--accent)]">Loan {money(member.outstanding_loan, business.currency)}</span> : null}</p><p className="text-lg font-black">{money(member.sales, business.currency)}</p></div>{canManage ? <div className="mt-3 flex gap-2 border-t border-[var(--line)] pt-3"><Button size="sm" variant="secondary" className="flex-1" leftIcon={<Wallet size={14} />} onClick={() => setPayingSalary(member)}>Payroll</Button><Button size="sm" variant="secondary" className="flex-1" leftIcon={<Edit3 size={14} />} onClick={() => setEditing(member)}>Edit</Button></div> : null}</div>)}</div>
        </> : <EmptyState icon={UsersRound} title="No team members" description="Add staff and assign a role. Employees receive a focused single-business workflow while admins get operational controls." action={canManage ? <Button leftIcon={<Plus size={16} />} onClick={() => setOpen(true)}>Add member</Button> : undefined} />}
      </Card>
      <MemberFormModal businessId={businessId} actorRole={business.my_role} actorFullControl={business.full_control} open={open} onClose={() => setOpen(false)} />
      <MemberEditModal businessId={businessId} actorRole={business.my_role} actorFullControl={business.full_control} actorIsFounder={business.is_founder} member={editing} open={Boolean(editing)} onClose={() => setEditing(null)} />
      <SalaryPaymentModal businessId={businessId} member={payingSalary} open={Boolean(payingSalary)} onClose={() => setPayingSalary(null)} currency={business.currency} />
    </div>
  );
}
function Mini({ label, value, icon: Icon, emphasis }: { label: string; value: string; icon: typeof UsersRound; emphasis?: boolean }) {
  return (
    <Card className={emphasis ? "border-[var(--brand)] bg-[var(--brand-deep)] p-4 text-[var(--on-brand-deep)]" : "p-4"}>
      <div className={cn("flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.11em]", emphasis ? "text-[var(--on-brand-deep)]/60" : "text-[var(--ink-soft)]")}><Icon size={14} className={emphasis ? "text-[var(--on-brand-deep)]" : "text-[var(--brand)]"} />{label}</div>
      <p className="mt-2 truncate text-xl font-black tracking-[-0.035em]">{value}</p>
    </Card>
  );
}
