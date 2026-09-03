"use client";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/fields";
import { cn } from "@/lib/utils";
import { format, startOfMonth, startOfYear, subDays, subMonths } from "date-fns";
import { CalendarDays } from "lucide-react";
import { useState } from "react";

export type DateRangeValue = { start: string; end: string };

function iso(date: Date) {
  return format(date, "yyyy-MM-dd");
}

export function defaultRange(): DateRangeValue {
  const now = new Date();
  return { start: iso(startOfMonth(now)), end: iso(now) };
}

export function DateRangeControl({ value, onChange, className }: { value: DateRangeValue; onChange: (range: DateRangeValue) => void; className?: string }) {
  const [custom, setCustom] = useState(false);
  const now = new Date();
  const presets = [
    { label: "This month", value: { start: iso(startOfMonth(now)), end: iso(now) } },
    { label: "Last 30 days", value: { start: iso(subDays(now, 29)), end: iso(now) } },
    { label: "Previous month", value: { start: iso(startOfMonth(subMonths(now, 1))), end: iso(subDays(startOfMonth(now), 1)) } },
    { label: "This year", value: { start: iso(startOfYear(now)), end: iso(now) } },
  ];

  const active = presets.find((preset) => preset.value.start === value.start && preset.value.end === value.end)?.label;

  return (
    <div className={cn("flex flex-col gap-2", className)}>
      <div className="flex max-w-full items-center gap-1 overflow-x-auto rounded-xl border border-[var(--line)] bg-white p-1 no-scrollbar">
        {presets.map((preset) => (
          <button
            type="button"
            key={preset.label}
            onClick={() => { setCustom(false); onChange(preset.value); }}
            className={cn("min-h-9 shrink-0 rounded-lg px-3 text-xs font-bold transition", active === preset.label && !custom ? "bg-[var(--brand-soft)] text-[var(--brand-deep)]" : "text-[var(--ink-soft)] hover:bg-[#f0f2ef]")}
          >
            {preset.label}
          </button>
        ))}
        <button type="button" onClick={() => setCustom((current) => !current)} className={cn("flex min-h-9 shrink-0 items-center gap-1.5 rounded-lg px-3 text-xs font-bold transition", custom || !active ? "bg-[var(--brand-soft)] text-[var(--brand-deep)]" : "text-[var(--ink-soft)] hover:bg-[#f0f2ef]")}>
          <CalendarDays size={14} /> Custom
        </button>
      </div>
      {custom ? (
        <div className="flex flex-wrap items-end gap-2 rounded-2xl border border-[var(--line)] bg-white p-3 shadow-[var(--shadow-sm)]">
          <label className="min-w-[145px] flex-1 text-xs font-bold text-[var(--ink-soft)]">From<Input className="mt-1" type="date" value={value.start} max={value.end} onChange={(event) => onChange({ ...value, start: event.target.value })} /></label>
          <label className="min-w-[145px] flex-1 text-xs font-bold text-[var(--ink-soft)]">To<Input className="mt-1" type="date" value={value.end} min={value.start} onChange={(event) => onChange({ ...value, end: event.target.value })} /></label>
          <Button size="sm" variant="secondary" onClick={() => setCustom(false)}>Done</Button>
        </div>
      ) : null}
    </div>
  );
}
