"use client";

import { PayrollHistoryList } from "@/components/dashboard/payroll-history-list";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { PageHeader } from "@/components/ui/page-header";
import { PageLoading } from "@/components/ui/loading";
import { api } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { MySalary } from "@/lib/types";
import { money } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { useParams } from "next/navigation";
import { EyeOff, HandCoins, Wallet2 } from "lucide-react";

export default function MyPayPage() {
  const { businessId } = useParams<{ businessId: string }>();
  const { getBusiness } = useBusinesses();
  const business = getBusiness(businessId);
  const query = useQuery({
    queryKey: ["my-salary", businessId],
    queryFn: async () => (await api.get<MySalary>(`/businesses/${businessId}/my-salary`)).data,
    enabled: Boolean(business),
  });

  if (!business || query.isLoading) return <PageLoading />;
  if (query.isError || !query.data) return <ErrorState onRetry={() => void query.refetch()} />;

  const data = query.data;
  const currency = business.currency;

  return (
    <div className="space-y-7">
      <PageHeader eyebrow={business.name} title="My pay" description="Your own pay rate, what's been paid so far, and any advance you still owe — set by an admin." />

      {data.visible ? (
        <>
          <section className="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <Mini label={data.pay_type === "fixed_salary" ? "Monthly rate" : "Pay type"} value={data.pay_type === "fixed_salary" ? money(data.salary_amount, currency) : "Commission"} />
            <Mini label="Accrued so far" value={money(data.accrued, currency)} />
            <Mini label="Paid so far" value={money(data.paid_total, currency)} />
            <Mini label={data.pending < 0 ? "Overpaid" : "Pending"} value={money(Math.abs(data.pending), currency)} emphasis={data.pending !== 0} danger={data.pending < 0} />
          </section>
          {data.pending < 0 ? <p className="text-xs font-semibold text-[var(--info)]">You were advanced more than you've earned — this will be deducted from what you earn next.</p> : null}
        </>
      ) : (
        <Card className="p-5">
          <div className="flex items-center gap-3 text-[var(--ink-soft)]"><EyeOff size={18} /><p className="text-sm font-semibold">Your pay details aren't shown to you yet. Ask an admin if you'd like this turned on.</p></div>
        </Card>
      )}

      {data.outstanding_loan > 0 ? (
        <Card className="border-[var(--accent)] p-5">
          <div className="flex items-start gap-3">
            <span className="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--accent-soft)] text-[var(--accent)]"><HandCoins size={17} /></span>
            <div>
              <p className="font-bold">Advance you still owe: {money(data.outstanding_loan, currency)}</p>
              <p className="mt-1 text-sm text-[var(--ink-soft)]">Given as a loan, separate from your regular pay. Ask an admin if you're unsure why.</p>
            </div>
          </div>
        </Card>
      ) : null}

      {data.visible ? (
        <Card className="overflow-hidden">
          <CardHeader title={<span className="flex items-center gap-2"><Wallet2 size={16} />Payment history</span>} description="Every payment, advance, loan or settlement recorded against you." />
          <CardBody>
            {data.payments.length ? <PayrollHistoryList payments={data.payments} currency={currency} /> : <EmptyState icon={Wallet2} title="Nothing recorded yet" description="Payments an admin records for you will show up here." />}
          </CardBody>
        </Card>
      ) : null}
    </div>
  );
}

function Mini({ label, value, emphasis, danger }: { label: string; value: string; emphasis?: boolean; danger?: boolean }) {
  return (
    <Card className={emphasis ? `border-[var(--brand)] p-4 ${danger ? "bg-[var(--info-soft)]" : "bg-[var(--brand-deep)] text-[var(--on-brand-deep)]"}` : "p-4"}>
      <p className={`text-[10px] font-bold uppercase tracking-[0.11em] ${emphasis && !danger ? "text-[var(--on-brand-deep)]/60" : "text-[var(--ink-soft)]"}`}>{label}</p>
      <p className="mt-1.5 truncate text-lg font-black tracking-[-0.03em]">{value}</p>
    </Card>
  );
}
