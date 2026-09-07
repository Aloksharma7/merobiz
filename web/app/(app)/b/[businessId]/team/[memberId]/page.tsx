"use client";

import { DateRangeControl, defaultRange, type DateRangeValue } from "@/components/dashboard/date-range-control";
import { PayrollPanel } from "@/components/dashboard/payroll-panel";
import { MemberEditModal } from "@/components/forms/member-edit-modal";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { PageHeader } from "@/components/ui/page-header";
import { PageLoading } from "@/components/ui/loading";
import { api } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { Member } from "@/lib/types";
import { humanize, money, number, prettyDate } from "@/lib/utils";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Edit3, Mail, Phone, Wallet2 } from "lucide-react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { useState } from "react";

function roleLabel(member: Member): string {
  if (member.is_founder) return "Founder";
  if (member.role === "owner") return member.full_control ? "Owner · full control" : "Co-owner";
  return humanize(member.role);
}

export default function TeamMemberDetailPage() {
  const { businessId, memberId } = useParams<{ businessId: string; memberId: string }>();
  const { getBusiness, can } = useBusinesses();
  const business = getBusiness(businessId);
  const canManage = can(business, "team.manage");
  const queryClient = useQueryClient();
  const [range, setRange] = useState<DateRangeValue>(defaultRange);
  const [editing, setEditing] = useState(false);

  const query = useQuery({
    queryKey: ["team-member", businessId, Number(memberId), range],
    queryFn: async () => (await api.get<{ data: Member }>(`/businesses/${businessId}/team/${memberId}`, { params: range })).data.data,
    enabled: Boolean(business) && canManage,
  });

  if (!business) return <PageLoading />;
  if (!canManage) return <ErrorState onRetry={() => void query.refetch()} />;
  if (query.isLoading) return <PageLoading />;
  if (query.isError || !query.data) return <ErrorState onRetry={() => void query.refetch()} />;

  const member = query.data;
  const currency = business.currency;

  return (
    <div className="space-y-7">
      <Link href={`/b/${businessId}/team`} className="inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--ink-soft)] hover:text-[var(--brand)]"><ArrowLeft size={15} />Back to team</Link>

      <PageHeader
        eyebrow={business.name}
        title={member.name}
        description={[member.title || roleLabel(member), member.email].filter(Boolean).join(" · ")}
        actions={<Button variant="secondary" leftIcon={<Edit3 size={16} />} onClick={() => setEditing(true)}>Edit member</Button>}
      />

      <div className="flex flex-wrap items-center gap-3">
        <Badge tone={member.active ? "success" : "neutral"}>{member.active ? "Active" : "Inactive"}</Badge>
        <Badge tone={member.pay_type === "fixed_salary" ? "info" : "neutral"}>{member.pay_type === "fixed_salary" ? "Fixed salary" : member.pay_type === "profit_share" ? "Profit based" : "Commission"}</Badge>
        {member.phone ? <span className="flex items-center gap-1.5 text-xs font-semibold text-[var(--ink-soft)]"><Phone size={13} />{member.phone}</span> : null}
        {member.email ? <span className="flex items-center gap-1.5 text-xs font-semibold text-[var(--ink-soft)]"><Mail size={13} />{member.email}</span> : null}
        {member.joined_at ? <span className="text-xs text-[var(--ink-soft)]">Joined {prettyDate(member.joined_at)}</span> : null}
      </div>

      <DateRangeControl value={range} onChange={setRange} />
      <section className="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <Stat label="Sales" value={money(member.sales, currency)} />
        <Stat label="Invoices" value={String(member.invoice_count)} />
        <Stat label="Profit share" value={`${number(member.profit_share_percent, 1)}%`} />
        <Stat label="Profit earned" value={money(member.profit_earned, currency)} />
      </section>

      <Card className="overflow-hidden">
        <CardHeader title={<span className="flex items-center gap-2"><Wallet2 size={16} />Payroll</span>} description="Rate, what's been paid, any advance still owed, and the full payment history." />
        <CardBody><PayrollPanel businessId={businessId} member={member} currency={currency} /></CardBody>
      </Card>

      <MemberEditModal
        businessId={businessId}
        actorRole={business.my_role}
        actorFullControl={business.full_control}
        actorIsFounder={business.is_founder}
        member={editing ? member : null}
        open={editing}
        onClose={() => { setEditing(false); void queryClient.invalidateQueries({ queryKey: ["team-member", businessId, Number(memberId)] }); }}
      />
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <Card className="p-4">
      <p className="text-[10px] font-bold uppercase tracking-[0.11em] text-[var(--ink-soft)]">{label}</p>
      <p className="mt-1.5 truncate text-lg font-black tracking-[-0.03em]">{value}</p>
    </Card>
  );
}
