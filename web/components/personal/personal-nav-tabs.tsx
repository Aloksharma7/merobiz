"use client";

import { cn } from "@/lib/utils";
import Link from "next/link";
import { usePathname } from "next/navigation";

const tabs = [
  { label: "Overview", href: "/personal" },
  { label: "Income", href: "/personal/income" },
  { label: "Expenses", href: "/personal/expenses" },
];

export function PersonalNavTabs() {
  const pathname = usePathname();

  return (
    <div className="flex max-w-full items-center gap-1 overflow-x-auto rounded-xl border border-[var(--line)] bg-white p-1 no-scrollbar">
      {tabs.map((tab) => {
        const active = tab.href === "/personal" ? pathname === "/personal" : pathname.startsWith(tab.href);
        return (
          <Link
            key={tab.href}
            href={tab.href}
            className={cn("min-h-9 shrink-0 rounded-lg px-3.5 text-xs font-bold transition flex items-center", active ? "bg-[var(--brand-soft)] text-[var(--brand-deep)]" : "text-[var(--ink-soft)] hover:bg-[#f0f2ef]")}
          >
            {tab.label}
          </Link>
        );
      })}
    </div>
  );
}
