import { cn } from "@/lib/utils";

export function Logo({ compact = false, inverse = false, className }: { compact?: boolean; inverse?: boolean; className?: string }) {
  return (
    <div className={cn("flex items-center gap-3", className)} aria-label="MeroBiz">
      <div className={cn(
        "relative grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-[13px] shadow-[0_8px_24px_rgb(5_41_31/0.16)]",
        inverse ? "bg-white text-[var(--brand-deep)]" : "bg-[var(--brand-deep)] text-white",
      )}>
        <span className="absolute -right-2 -top-2 h-6 w-6 rounded-full bg-[var(--accent)]" />
        <svg viewBox="0 0 28 28" className="relative h-6 w-6" aria-hidden="true">
          <path d="M5 21V8.5c0-.9.7-1.5 1.5-1.5h3.2l4.3 6 4.3-6h3.2c.8 0 1.5.6 1.5 1.5V21h-4.5v-7.2L14 19l-4.5-5.2V21H5Z" fill="currentColor" />
        </svg>
      </div>
      {!compact ? (
        <div className="leading-none">
          <div className={cn("text-[1.05rem] font-black tracking-[-0.035em]", inverse ? "text-white" : "text-[var(--ink)]")}>MeroBiz</div>
          <div className={cn("mt-1 text-[10px] font-semibold uppercase tracking-[0.16em]", inverse ? "text-white/65" : "text-[var(--ink-soft)]")}>Business OS</div>
        </div>
      ) : null}
    </div>
  );
}
