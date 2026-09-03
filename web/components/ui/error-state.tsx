"use client";

import { AlertTriangle, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";

export function ErrorState({ title = "We could not load this page", description = "Check the API connection and try again.", onRetry }: { title?: string; description?: string; onRetry?: () => void }) {
  return (
    <div className="rounded-[var(--radius)] border border-[#efd0d0] bg-white p-8 text-center shadow-[var(--shadow-sm)]">
      <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[var(--danger-soft)] text-[var(--danger)]"><AlertTriangle size={22} /></div>
      <h2 className="mt-4 font-bold">{title}</h2>
      <p className="mx-auto mt-1 max-w-md text-sm leading-6 text-[var(--ink-soft)]">{description}</p>
      {onRetry ? <Button className="mt-5" variant="secondary" leftIcon={<RefreshCw size={16} />} onClick={onRetry}>Try again</Button> : null}
    </div>
  );
}
