"use client";

import { Button, LinkButton } from "@/components/ui/button";
import { AlertTriangle, RefreshCw } from "lucide-react";
import { useEffect } from "react";

export default function GlobalErrorPage({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <div className="grid min-h-screen place-items-center bg-[var(--surface-soft)] p-6">
      <div className="w-full max-w-md rounded-[var(--radius)] border border-[#efd0d0] bg-white p-8 text-center shadow-[var(--shadow-sm)]">
        <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[var(--danger-soft)] text-[var(--danger)]"><AlertTriangle size={22} /></div>
        <h1 className="mt-4 text-lg font-extrabold tracking-[-0.02em] text-[var(--ink)]">Something went wrong</h1>
        <p className="mx-auto mt-1.5 max-w-sm text-sm leading-6 text-[var(--ink-soft)]">This page hit an unexpected error. Try again, or head back to your workspace.</p>
        <div className="mt-6 flex justify-center gap-2">
          <Button variant="secondary" leftIcon={<RefreshCw size={16} />} onClick={reset}>Try again</Button>
          <LinkButton href="/">Go home</LinkButton>
        </div>
      </div>
    </div>
  );
}
