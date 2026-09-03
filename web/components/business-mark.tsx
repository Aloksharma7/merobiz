import { brandingFor } from "@/lib/branding";
import type { Business } from "@/lib/types";
import { cn } from "@/lib/utils";

type Props = {
  business: Business;
  className?: string;
  imageClassName?: string;
  compact?: boolean;
  invoice?: boolean;
};

export function BusinessMark({ business, className, imageClassName, compact = false, invoice = false }: Props) {
  const brand = brandingFor(business);
  const allowed = invoice ? brand.showLogoInvoice : brand.showLogoWorkspace;
  const initials = (business.code || business.name)
    .replace(/[^A-Za-z0-9]/g, "")
    .slice(0, compact ? 3 : 5)
    .toUpperCase() || "BIZ";

  if (allowed && brand.logoUrl) {
    return (
      <span className={cn("grid shrink-0 place-items-center overflow-hidden rounded-xl border border-black/5 bg-white shadow-sm", compact ? "h-9 w-9" : "h-12 w-12", className)}>
        {/* A regular img is intentional here because the Laravel storage host is configurable at runtime. */}
        <img src={brand.logoUrl} alt={`${business.name} logo`} className={cn("h-full w-full object-contain p-1.5", imageClassName)} />
      </span>
    );
  }

  return (
    <span className={cn("grid shrink-0 place-items-center rounded-xl bg-[var(--accent)] px-2 font-black text-[var(--brand-deep)]", compact ? "h-9 min-w-9 text-[10px]" : "h-12 min-w-12 text-xs", className)} aria-label={`${business.name} mark`}>
      {initials}
    </span>
  );
}
