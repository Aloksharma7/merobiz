"use client";

import { WriterFormModal } from "@/components/forms/writer-form-modal";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/fields";
import { PageLoading, TableLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api, apiError } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { Paginated, Writer } from "@/lib/types";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Edit3, Mail, PenTool, Phone, Plus, Search, Trash2 } from "lucide-react";
import Link from "next/link";
import { useParams, useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { toast } from "sonner";

export default function WritersPage() {
  return (
    <Suspense fallback={<PageLoading />}>
      <WritersPageContent />
    </Suspense>
  );
}

function WritersPageContent() {
  const { businessId } = useParams<{ businessId: string }>();
  const router = useRouter();
  const searchParams = useSearchParams();
  const { getBusiness, can } = useBusinesses();
  const business = getBusiness(businessId);
  const canManage = can(business, "writers.manage");
  const featureEnabled = Boolean(business?.is_installment);
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Writer | null>(null);
  useEffect(() => { if (canManage && searchParams.get("new") === "1") setOpen(true); }, [canManage, searchParams]);
  const query = useQuery({ queryKey: ["writers", businessId, { search, page }], queryFn: async () => (await api.get<Paginated<Writer>>(`/businesses/${businessId}/writers`, { params: { search: search || undefined, page, per_page: 20 } })).data, placeholderData: keepPreviousData, enabled: canManage && featureEnabled });
  const archive = useMutation({ mutationFn: async (id: number) => api.delete(`/businesses/${businessId}/writers/${id}`), onSuccess: async () => { await queryClient.invalidateQueries({ queryKey: ["writers", businessId] }); toast.success("Writer archived"); }, onError: (error) => toast.error("Could not archive writer", { description: apiError(error) }) });
  if (!business) return <PageLoading />;
  if (!featureEnabled) {
    return (
      <div className="space-y-7">
        <PageHeader eyebrow={business.name} title="Writers" description="This is an installment / project-based business feature." />
        <Card><EmptyState icon={PenTool} title="Writers aren't available for this business" description="A writer roster is only available for installment-category businesses, set when a business is created." /></Card>
      </div>
    );
  }
  if (query.isError) return <ErrorState onRetry={() => void query.refetch()} />;
  const rows = query.data?.data ?? [];
  return (
    <div className="space-y-7">
      <PageHeader eyebrow={business.name} title="Writers" description="The roster of writers who can be assigned to service line items on invoices." actions={canManage ? <Button leftIcon={<Plus size={17} />} onClick={() => setOpen(true)}>Add writer</Button> : undefined} />
      <Card className="overflow-hidden">
        <div className="border-b border-[var(--line)] p-4"><label className="relative block max-w-xl"><span className="sr-only">Search writers</span><Search size={17} className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--ink-soft)]" /><Input className="pl-10" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder="Search name, phone or email" /></label></div>
        {query.isLoading ? <TableLoading /> : rows.length ? <>
          <div className="hidden overflow-x-auto md:block"><table className="w-full min-w-[680px] text-left text-sm"><thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Writer</th><th className="px-4 py-3 font-bold">Contact</th><th className="px-4 py-3 font-bold">Status</th><th className="px-5 py-3 text-right font-bold">Actions</th></tr></thead><tbody className="divide-y divide-[var(--line)]">{rows.map((writer) => <tr key={writer.id} className="cursor-pointer hover:bg-[var(--surface-soft)]" onClick={() => router.push(`/b/${businessId}/writers/${writer.id}`)}><td className="px-5 py-3.5"><Link href={`/b/${businessId}/writers/${writer.id}`} onClick={(event) => event.stopPropagation()} className="flex items-center gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-[var(--brand-soft)] text-sm font-black text-[var(--brand)]">{writer.name.slice(0, 2).toUpperCase()}</span><div><p className="font-bold hover:text-[var(--brand)]">{writer.name}</p>{writer.notes ? <p className="mt-0.5 max-w-[260px] truncate text-xs text-[var(--ink-soft)]">{writer.notes}</p> : null}</div></Link></td><td className="px-4 py-3.5"><p className="text-sm">{writer.phone || "—"}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{writer.email || "No email"}</p></td><td className="px-4 py-3.5"><Badge tone={writer.active ? "success" : "neutral"}>{writer.active ? "Active" : "Inactive"}</Badge></td><td className="px-5 py-3.5" onClick={(event) => event.stopPropagation()}><div className="flex justify-end gap-1"><button type="button" onClick={() => setEditing(writer)} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--brand-soft)] hover:text-[var(--brand)]" aria-label="Edit writer"><Edit3 size={15} /></button><button type="button" onClick={() => { if (window.confirm("Archive this writer? Past assignments remain unchanged.")) archive.mutate(writer.id); }} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--danger-soft)] hover:text-[var(--danger)]" aria-label="Archive writer"><Trash2 size={15} /></button></div></td></tr>)}</tbody></table></div>
          <div className="divide-y divide-[var(--line)] md:hidden">{rows.map((writer) => <Link href={`/b/${businessId}/writers/${writer.id}`} key={writer.id} className="block w-full p-4 text-left hover:bg-[var(--surface-soft)]"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="truncate font-bold">{writer.name}</p><p className="mt-1 flex items-center gap-1.5 text-xs text-[var(--ink-soft)]"><Phone size={12} />{writer.phone || "No phone"}</p><p className="mt-1 flex items-center gap-1.5 truncate text-xs text-[var(--ink-soft)]"><Mail size={12} />{writer.email || "No email"}</p></div><Badge tone={writer.active ? "success" : "neutral"}>{writer.active ? "Active" : "Inactive"}</Badge></div></Link>)}</div>
          <Pager current={query.data?.meta.current_page ?? 1} last={query.data?.meta.last_page ?? 1} onChange={setPage} />
        </> : <EmptyState icon={PenTool} title={search ? "No matching writers" : "No writers yet"} description={search ? "Try another name, number or email address." : "Add writers so they can be assigned to service invoice items."} action={canManage ? <Button leftIcon={<Plus size={16} />} onClick={() => setOpen(true)}>Add writer</Button> : undefined} />}
      </Card>
      <WriterFormModal businessId={businessId} open={canManage && (open || Boolean(editing))} writer={editing} onClose={() => { setOpen(false); setEditing(null); }} />
    </div>
  );
}
function Pager({ current, last, onChange }: { current: number; last: number; onChange: (page: number) => void }) { if (last <= 1) return null; return <div className="flex items-center justify-between border-t border-[var(--line)] p-3"><Button size="sm" variant="secondary" disabled={current <= 1} onClick={() => onChange(current - 1)}>Previous</Button><span className="text-xs text-[var(--ink-soft)]">Page {current} of {last}</span><Button size="sm" variant="secondary" disabled={current >= last} onClick={() => onChange(current + 1)}>Next</Button></div>; }
