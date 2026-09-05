"use client";

import { BusinessFormModal } from "@/components/forms/business-form-modal";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { PageLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { useAuth } from "@/lib/auth-context";
import { businessThemeStyle } from "@/lib/branding";
import { useBusinesses } from "@/lib/business-context";
import { humanize } from "@/lib/utils";
import { ArrowRight, Building2, CircleGauge, Plus, ShieldCheck } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

export default function BusinessesPage() {
  const { user } = useAuth();
  const { businesses, isLoading } = useBusinesses();
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const employeeWorkspace = user?.workspace?.mode === "employee";
  const canCreateBusiness = businesses.length === 0 || businesses.some((business) => business.my_role === "owner");

  useEffect(() => {
    if (employeeWorkspace && user?.workspace?.business_id) {
      router.replace(`/b/${user?.workspace?.business_id}`);
    }
  }, [employeeWorkspace, router, user?.workspace?.business_id]);

  if (isLoading || employeeWorkspace) return <PageLoading />;

  return (
    <div className="space-y-7">
      <PageHeader eyebrow="Workspace" title="Your businesses" description="Each business keeps separate customers, invoices, expenses, staff access and financial records." actions={canCreateBusiness ? <Button leftIcon={<Plus size={17} />} onClick={() => setOpen(true)}>Add business</Button> : undefined} />

      {businesses.length === 0 ? (
        <Card><EmptyState icon={Building2} title="No businesses yet" description="Create one workspace now. You can add the other businesses whenever you are ready." action={canCreateBusiness ? <Button leftIcon={<Plus size={17} />} onClick={() => setOpen(true)}>Create first business</Button> : undefined} /></Card>
      ) : (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {businesses.map((business) => (
            <Link href={`/b/${business.id}`} key={business.id} className="group block" style={businessThemeStyle(business)}>
              <Card className="h-full overflow-hidden transition duration-200 hover:-translate-y-0.5 hover:border-[#adc0b1] hover:shadow-[0_16px_40px_rgb(17_48_35/0.08)]">
                <div className="relative h-28 overflow-hidden bg-[var(--brand-deep)] p-5 text-[var(--on-brand-deep)] surface-grid">
                  <div className="absolute -right-7 -top-9 h-28 w-28 rounded-full bg-[var(--accent)]/23" />
                  <div className="relative flex items-start justify-between gap-4"><span className="grid h-12 w-12 place-items-center rounded-2xl bg-white/10 text-sm font-black backdrop-blur">{business.code}</span><Badge tone="brand">{business.status}</Badge></div>
                </div>
                <div className="p-5">
                  <div className="flex items-start justify-between gap-4"><div className="min-w-0"><h2 className="truncate text-lg font-black tracking-[-0.025em]">{business.name}</h2><p className="mt-1 text-sm text-[var(--ink-soft)]">{humanize(business.business_type)}</p></div><ArrowRight size={19} className="mt-1 shrink-0 text-[var(--ink-soft)] transition group-hover:translate-x-1 group-hover:text-[var(--brand)]" /></div>
                  <div className="mt-5 grid grid-cols-2 gap-2">
                    <div className="rounded-xl bg-[var(--surface-soft)] p-3"><div className="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.1em] text-[var(--ink-soft)]"><CircleGauge size={13} />Your role</div><p className="mt-1.5 text-sm font-bold">{humanize(business.my_role)}</p></div>
                    <div className="rounded-xl bg-[var(--surface-soft)] p-3"><div className="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.1em] text-[var(--ink-soft)]"><ShieldCheck size={13} />Your share</div><p className="mt-1.5 text-sm font-bold">{business.profit_share_percent}% profit</p></div>
                  </div>
                  <p className="mt-4 truncate text-xs text-[var(--ink-soft)]">{business.address || business.email || "Business details can be completed in Settings"}</p>
                </div>
              </Card>
            </Link>
          ))}
        </div>
      )}
      {canCreateBusiness ? <BusinessFormModal open={open} onClose={() => setOpen(false)} /> : null}
    </div>
  );
}
