import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { humanize, money } from "@/lib/utils";
import { ArrowRight, HandCoins } from "lucide-react";
import Link from "next/link";

export function BusinessPerformanceCard({ row }: { row: {
  id: number; name: string; code: string; currency: string; business_type: string; my_role: string;
  ownership_percent: number; profit_share_percent: number; can_view_financials: boolean;
  metrics: { net_sales: number; net_profit: number; attributable_profit?: number; receivables: number };
} }) {
  const owner = row.my_role === "owner";
  return (
    <Link href={`/b/${row.id}`} className="group block">
      <Card className="h-full overflow-hidden transition duration-200 hover:-translate-y-0.5 hover:border-[#b7c8bb] hover:shadow-[0_16px_36px_rgb(17_48_35/0.08)]">
        <div className="h-1.5 bg-gradient-to-r from-[var(--brand)] via-[#3d9a79] to-[var(--accent)]" />
        <div className="p-5">
          <div className="flex items-start justify-between gap-4">
            <div className="flex min-w-0 items-center gap-3">
              <div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[var(--brand-soft)] text-sm font-black text-[var(--brand-deep)]">{row.code.slice(0, 3)}</div>
              <div className="min-w-0">
                <h3 className="truncate font-extrabold tracking-[-0.015em]">{row.name}</h3>
                <p className="mt-0.5 text-xs text-[var(--ink-soft)]">{owner ? `${row.ownership_percent}% ownership` : humanize(row.my_role)}</p>
              </div>
            </div>
            <ArrowRight size={18} className="mt-2 shrink-0 text-[var(--ink-soft)] transition group-hover:translate-x-1 group-hover:text-[var(--brand)]" />
          </div>
          <div className="mt-6 grid grid-cols-2 gap-3">
            <div className="rounded-2xl bg-[var(--surface-soft)] p-3.5"><p className="text-[10px] font-bold uppercase tracking-[0.11em] text-[var(--ink-soft)]">Net sales</p><p className="mt-1 truncate text-lg font-black tracking-[-0.03em]">{money(row.metrics.net_sales, row.currency)}</p></div>
            <div className="rounded-2xl bg-[var(--brand-deep)] p-3.5 text-white"><p className="text-[10px] font-bold uppercase tracking-[0.11em] text-white/50">{owner ? "Your profit" : "Net profit"}</p><p className="mt-1 truncate text-lg font-black tracking-[-0.03em]">{money(owner ? row.metrics.attributable_profit ?? 0 : row.metrics.net_profit, row.currency)}</p></div>
          </div>
          <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-[var(--line)] pt-4">
            <div className="flex items-center gap-2 text-xs text-[var(--ink-soft)]"><HandCoins size={15} />Receivable {money(row.metrics.receivables, row.currency)}</div>
            <Badge tone="success">Live</Badge>
          </div>
        </div>
      </Card>
    </Link>
  );
}
