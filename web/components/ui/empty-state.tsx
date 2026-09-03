import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

export function EmptyState({ icon: Icon, title, description, action }: { icon: LucideIcon; title: string; description: string; action?: ReactNode }) {
  return (
    <div className="flex min-h-64 flex-col items-center justify-center px-5 py-12 text-center">
      <div className="grid h-14 w-14 place-items-center rounded-2xl bg-[var(--brand-soft)] text-[var(--brand)]">
        <Icon size={25} />
      </div>
      <h3 className="mt-4 text-base font-bold text-[var(--ink)]">{title}</h3>
      <p className="mt-1.5 max-w-sm text-sm leading-6 text-[var(--ink-soft)]">{description}</p>
      {action ? <div className="mt-5">{action}</div> : null}
    </div>
  );
}
