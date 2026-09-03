import { Card } from "@/components/ui/card";
import { cn, money, percentChangeLabel } from "@/lib/utils";
import { ArrowDownRight, ArrowUpRight, Minus, type LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

export function MetricCard({
  label,
  value,
  currency = "NPR",
  icon: Icon,
  change,
  hint,
  emphasis = false,
  valueFormatter,
}: {
  label: string;
  value: number;
  currency?: string;
  icon: LucideIcon;
  change?: number;
  hint?: ReactNode;
  emphasis?: boolean;
  valueFormatter?: (value: number) => ReactNode;
}) {
  const Direction = change === undefined || change === 0 ? Minus : change > 0 ? ArrowUpRight : ArrowDownRight;
  return (
    <Card className={cn("relative overflow-hidden p-5", emphasis && "border-[var(--brand)] bg-[var(--brand-deep)] text-white shadow-[0_16px_40px_rgb(11_60_49/0.16)]")}>
      {emphasis ? <div className="absolute -right-8 -top-10 h-28 w-28 rounded-full bg-[var(--accent)]/20" /> : null}
      <div className="relative flex items-start justify-between gap-3">
        <div className={cn("grid h-10 w-10 place-items-center rounded-xl", emphasis ? "bg-white/10 text-[#b9e2d2]" : "bg-[var(--brand-soft)] text-[var(--brand)]")}><Icon size={19} /></div>
        {change !== undefined ? (
          <div className={cn("flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-bold", emphasis ? "bg-white/10 text-white/72" : change > 0 ? "bg-[var(--brand-soft)] text-[var(--brand)]" : change < 0 ? "bg-[var(--danger-soft)] text-[var(--danger)]" : "bg-[#eef1ee] text-[var(--ink-soft)]")} title={percentChangeLabel(change)}>
            <Direction size={12} />{Math.abs(change).toFixed(1)}%
          </div>
        ) : null}
      </div>
      <p className={cn("relative mt-5 text-xs font-bold uppercase tracking-[0.11em]", emphasis ? "text-white/55" : "text-[var(--ink-soft)]")}>{label}</p>
      <div className="relative mt-1.5 truncate text-[1.65rem] font-black tracking-[-0.045em]">{valueFormatter ? valueFormatter(value) : money(value, currency)}</div>
      {hint ? <div className={cn("relative mt-2 text-xs leading-5", emphasis ? "text-white/52" : "text-[var(--ink-soft)]")}>{hint}</div> : null}
    </Card>
  );
}
