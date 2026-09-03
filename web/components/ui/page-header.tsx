import type { ReactNode } from "react";

export function PageHeader({ eyebrow, title, description, actions }: { eyebrow?: ReactNode; title: string; description?: string; actions?: ReactNode }) {
  return (
    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div className="min-w-0">
        {eyebrow ? <div className="mb-1.5 text-xs font-bold uppercase tracking-[0.14em] text-[var(--brand)]">{eyebrow}</div> : null}
        <h1 className="text-balance text-2xl font-black tracking-[-0.035em] text-[var(--ink)] sm:text-[2rem]">{title}</h1>
        {description ? <p className="mt-2 max-w-2xl text-sm leading-6 text-[var(--ink-soft)] sm:text-base">{description}</p> : null}
      </div>
      {actions ? <div className="flex shrink-0 flex-wrap gap-2">{actions}</div> : null}
    </div>
  );
}
