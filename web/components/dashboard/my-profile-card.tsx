import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { Wallet2 } from "lucide-react";

export function MyProfileCard({
  name,
  email,
  initials,
  roleLabel,
  ownershipPercent,
  profitSharePercent,
  onLogProfit,
  className,
}: {
  name: string;
  email?: string | null;
  initials: string;
  roleLabel: string;
  ownershipPercent?: number | null;
  profitSharePercent?: number | null;
  onLogProfit?: () => void;
  className?: string;
}) {
  const showShares = ownershipPercent !== undefined && ownershipPercent !== null;

  return (
    <Card className={cn("p-4", className)}>
      <div className="flex items-center gap-3">
        <span className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[var(--brand-deep)] text-sm font-black text-[var(--on-brand-deep)]">{initials}</span>
        <div className="min-w-0">
          <p className="truncate text-sm font-black tracking-[-0.01em] text-[var(--ink)]">{name}</p>
          <p className="mt-0.5 truncate text-xs font-semibold text-[var(--brand)]">{roleLabel}</p>
        </div>
      </div>
      {showShares ? (
        <div className="mt-3 flex gap-5 border-t border-[var(--line)] pt-3">
          <div>
            <p className="text-[9px] font-bold uppercase tracking-[0.11em] text-[var(--ink-soft)]">Ownership</p>
            <p className="mt-0.5 text-sm font-black">{ownershipPercent}%</p>
          </div>
          <div>
            <p className="text-[9px] font-bold uppercase tracking-[0.11em] text-[var(--ink-soft)]">Profit share</p>
            <p className="mt-0.5 text-sm font-black">{profitSharePercent}%</p>
          </div>
        </div>
      ) : null}
      {onLogProfit ? <Button size="sm" variant="secondary" leftIcon={<Wallet2 size={14} />} className="mt-3 w-full" onClick={onLogProfit}>Log profit taken</Button> : null}
    </Card>
  );
}
