"use client";

import { DateRangeControl, defaultRange, type DateRangeValue } from "@/components/dashboard/date-range-control";
import { MemberEditModal } from "@/components/forms/member-edit-modal";
import { MemberFormModal } from "@/components/forms/member-form-modal";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { PageLoading, TableLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { Member } from "@/lib/types";
import { humanize, money, number } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { Award, Edit3, Plus, ReceiptText, TrendingUp, UsersRound } from "lucide-react";
import { useParams } from "next/navigation";
import { useState } from "react";

export default function TeamPage() {
  const { businessId } = useParams<{ businessId: string }>();
  const { getBusiness, can } = useBusinesses();
  const business = getBusiness(businessId);
  const [range, setRange] = useState<DateRangeValue>(defaultRange);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Member | null>(null);
  const query = useQuery({ queryKey: ["team", businessId, range], queryFn: async () => (await api.get<{ data: Member[] }>(`/businesses/${businessId}/team`, { params: range })).data.data });
  const rows = query.data ?? [];
  const canManage = can(business, "team.manage");
  const stats = { active: rows.filter((row) => row.active).length, sales: rows.reduce((sum, row) => sum + row.sales, 0), invoices: rows.reduce((sum, row) => sum + row.invoice_count, 0), commission: rows.reduce((sum, row) => sum + row.commission_earned, 0) };
  const maxSales = Math.max(...rows.map((row) => row.sales), 1);
  if (!business) return <PageLoading />;
  if (query.isError) return <ErrorState onRetry={() => void query.refetch()} />;
  return (
    <div className="space-y-7">
      <PageHeader eyebrow={business.name} title="Team performance" description="Give each person only the access they need and compare sales without exposing partner finances." actions={canManage ? <Button leftIcon={<Plus size={17} />} onClick={() => setOpen(true)}>Add member</Button> : undefined} />
      <DateRangeControl value={range} onChange={setRange} />
      <section className="grid grid-cols-2 gap-3 xl:grid-cols-4"><Mini label="Active members" value={String(stats.active)} icon={UsersRound} /><Mini label="Team sales" value={money(stats.sales, business.currency)} icon={TrendingUp} /><Mini label="Invoices" value={String(stats.invoices)} icon={ReceiptText} /><Mini label="Commission" value={money(stats.commission, business.currency)} icon={Award} /></section>
      <Card className="overflow-hidden">
        {query.isLoading ? <TableLoading /> : rows.length ? <>
          <div className="hidden overflow-x-auto md:block"><table className="w-full min-w-[870px] text-left text-sm"><thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Member</th><th className="px-4 py-3 font-bold">Role</th><th className="px-4 py-3 text-right font-bold">Sales</th><th className="px-4 py-3 text-right font-bold">Invoices</th><th className="px-4 py-3 text-right font-bold">Commission</th><th className="px-4 py-3 text-right font-bold">Profit share</th><th className="px-5 py-3 text-right font-bold">Action</th></tr></thead><tbody className="divide-y divide-[var(--line)]">{[...rows].sort((a, b) => b.sales - a.sales).map((member, index) => <tr key={member.id} className="hover:bg-[var(--surface-soft)]"><td className="px-5 py-3.5"><div className="flex items-center gap-3"><span className="relative grid h-10 w-10 place-items-center rounded-xl bg-[var(--brand-soft)] text-xs font-black text-[var(--brand)]">{member.initials}{index === 0 && member.sales > 0 ? <Award size={13} className="absolute -right-1 -top-1 rounded-full bg-[var(--accent)] p-0.5 text-[var(--brand-deep)]" /> : null}</span><div><p className="font-bold">{member.name}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{member.title || member.email}</p></div></div></td><td className="px-4 py-3.5"><Badge tone={member.active ? "success" : "neutral"}>{humanize(member.role)}</Badge></td><td className="px-4 py-3.5 text-right"><p className="font-black">{money(member.sales, business.currency)}</p><div className="ml-auto mt-1.5 h-1.5 w-24 overflow-hidden rounded-full bg-[#e9ede9]"><div className="h-full rounded-full bg-[var(--brand)]" style={{ width: `${Math.max(member.sales > 0 ? 6 : 0, member.sales / maxSales * 100)}%` }} /></div></td><td className="px-4 py-3.5 text-right font-bold">{member.invoice_count}</td><td className="px-4 py-3.5 text-right font-semibold">{money(member.commission_earned, business.currency)}</td><td className="px-4 py-3.5 text-right text-[var(--ink-soft)]">{number(member.profit_share_percent, 1)}%</td><td className="px-5 py-3.5 text-right">{canManage ? <button type="button" onClick={() => setEditing(member)} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--brand-soft)] hover:text-[var(--brand)] ml-auto" aria-label="Edit member"><Edit3 size={15} /></button> : null}</td></tr>)}</tbody></table></div>
          <div className="grid gap-3 p-4 sm:grid-cols-2 md:hidden">{[...rows].sort((a, b) => b.sales - a.sales).map((member) => <button type="button" disabled={!canManage} onClick={() => setEditing(member)} key={member.id} className="rounded-2xl border border-[var(--line)] p-4 text-left enabled:transition enabled:hover:border-[#b3c3b6] enabled:hover:bg-[var(--surface-soft)]"><div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-[var(--brand-soft)] text-xs font-black text-[var(--brand)]">{member.initials}</span><div className="min-w-0 flex-1"><p className="truncate font-bold">{member.name}</p><p className="mt-0.5 truncate text-xs text-[var(--ink-soft)]">{humanize(member.role)} · {member.invoice_count} invoices</p></div><Badge tone={member.active ? "success" : "neutral"}>{member.active ? "Active" : "Off"}</Badge></div><div className="mt-4 flex items-end justify-between"><p className="text-xs text-[var(--ink-soft)]">Commission<br /><strong className="text-[var(--ink)]">{money(member.commission_earned, business.currency)}</strong></p><p className="text-lg font-black">{money(member.sales, business.currency)}</p></div></button>)}</div>
        </> : <EmptyState icon={UsersRound} title="No team members" description="Add staff and assign a role. Employees receive a focused single-business workflow while admins get operational controls." action={canManage ? <Button leftIcon={<Plus size={16} />} onClick={() => setOpen(true)}>Add member</Button> : undefined} />}
      </Card>
      <MemberFormModal businessId={businessId} actorRole={business.my_role} open={open} onClose={() => setOpen(false)} />
      <MemberEditModal businessId={businessId} actorRole={business.my_role} member={editing} open={Boolean(editing)} onClose={() => setEditing(null)} />
    </div>
  );
}
function Mini({ label, value, icon: Icon }: { label: string; value: string; icon: typeof UsersRound }) { return <Card className="p-4"><div className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.11em] text-[var(--ink-soft)]"><Icon size={14} className="text-[var(--brand)]" />{label}</div><p className="mt-2 truncate text-xl font-black tracking-[-0.035em]">{value}</p></Card>; }
