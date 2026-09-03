import { cn } from "@/lib/utils";
import type { HTMLAttributes, ReactNode } from "react";

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn("rounded-[var(--radius)] border border-[var(--line)] bg-[var(--surface)] shadow-[var(--shadow-sm)] transition-[border-color,box-shadow,transform] duration-200 ease-out", className)} {...props} />;
}

export function CardHeader({ title, description, action, className }: { title: ReactNode; description?: ReactNode; action?: ReactNode; className?: string }) {
  return (
    <div className={cn("flex items-start justify-between gap-4 border-b border-[var(--line)] px-5 py-4 sm:px-6", className)}>
      <div className="min-w-0">
        <h2 className="text-base font-bold tracking-[-0.01em] text-[var(--ink)]">{title}</h2>
        {description ? <p className="mt-1 text-sm leading-5 text-[var(--ink-soft)]">{description}</p> : null}
      </div>
      {action ? <div className="shrink-0">{action}</div> : null}
    </div>
  );
}

export function CardBody({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn("p-5 sm:p-6", className)} {...props} />;
}
