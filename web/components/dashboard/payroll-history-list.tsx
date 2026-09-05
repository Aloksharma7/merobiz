import { Badge } from "@/components/ui/badge";
import type { SalaryEntryType, SalaryPaymentRecord } from "@/lib/types";
import { humanize, money, prettyDate } from "@/lib/utils";

export const ENTRY_TYPE_TONE: Record<SalaryEntryType, "neutral" | "info" | "warning" | "success"> = {
  payment: "neutral",
  advance: "info",
  loan: "warning",
  write_off: "success",
};

export function PayrollHistoryList({ payments, currency, emptyLabel = "No payments recorded yet." }: { payments: SalaryPaymentRecord[]; currency: string; emptyLabel?: string }) {
  if (!payments.length) return <p className="text-xs text-[var(--ink-soft)]">{emptyLabel}</p>;

  return (
    <div className="space-y-2">
      {payments.map((payment) => (
        <div key={payment.id} className="flex items-center justify-between gap-3 rounded-xl border border-[var(--line)] p-3 text-sm">
          <div className="min-w-0">
            <div className="flex items-center gap-2"><Badge tone={ENTRY_TYPE_TONE[payment.entry_type]}>{payment.entry_type === "write_off" ? "Settled" : humanize(payment.entry_type)}</Badge><span className="text-xs text-[var(--ink-soft)]">{prettyDate(payment.payment_date)}</span></div>
            {payment.notes ? <p className="mt-1 truncate text-xs text-[var(--ink-soft)]">{payment.notes}</p> : null}
          </div>
          <div className="shrink-0 text-right">
            <p className="font-black">{money(payment.amount, currency)}</p>
            <p className="text-[11px] text-[var(--ink-soft)]">{humanize(payment.method)}{payment.recorded_by ? ` · ${payment.recorded_by}` : ""}</p>
          </div>
        </div>
      ))}
    </div>
  );
}
