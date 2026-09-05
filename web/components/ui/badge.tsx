import { cn, humanize } from "@/lib/utils";
import type { HTMLAttributes } from "react";

type Tone = "neutral" | "success" | "warning" | "danger" | "info" | "brand";

export function Badge({ className, tone = "neutral", children, ...props }: HTMLAttributes<HTMLSpanElement> & { tone?: Tone }) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold",
        tone === "neutral" && "bg-[#edf0ed] text-[#53605a]",
        tone === "success" && "bg-[var(--brand-soft)] text-[var(--ink)]",
        tone === "warning" && "bg-[var(--accent-soft)] text-[#85550d]",
        tone === "danger" && "bg-[var(--danger-soft)] text-[var(--danger)]",
        tone === "info" && "bg-[var(--info-soft)] text-[var(--info)]",
        tone === "brand" && "bg-[var(--brand)] text-white",
        className,
      )}
      {...props}
    >
      {typeof children === "string" ? humanize(children) : children}
    </span>
  );
}

export function statusTone(status: string): Tone {
  if (["paid", "approved", "active", "closed"].includes(status)) return "success";
  if (["partial", "pending", "draft"].includes(status)) return "warning";
  if (["overdue", "rejected", "cancelled", "refunded", "inactive"].includes(status)) return "danger";
  if (["issued"].includes(status)) return "info";
  return "neutral";
}
