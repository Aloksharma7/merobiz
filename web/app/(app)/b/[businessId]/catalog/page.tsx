"use client";

import { ProductFormModal } from "@/components/forms/product-form-modal";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input, Select } from "@/components/ui/fields";
import { PageLoading, TableLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api, apiError } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { Paginated, Product } from "@/lib/types";
import { humanize, money, number } from "@/lib/utils";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Boxes, Edit3, PackageCheck, Plus, Search, Trash2 } from "lucide-react";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { toast } from "sonner";

export default function CatalogPage() {
  return (
    <Suspense fallback={<PageLoading />}>
      <CatalogPageContent />
    </Suspense>
  );
}

function CatalogPageContent() {
  const { businessId } = useParams<{ businessId: string }>();
  const searchParams = useSearchParams();
  const { getBusiness, can } = useBusinesses();
  const business = getBusiness(businessId);
  const canManage = can(business, "products.manage");
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [type, setType] = useState("all");
  const [page, setPage] = useState(1);
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<Product | null>(null);
  useEffect(() => { if (canManage && searchParams.get("new") === "1") setOpen(true); }, [canManage, searchParams]);
  const query = useQuery({ queryKey: ["products", businessId, { search, type, page }], queryFn: async () => (await api.get<Paginated<Product>>(`/businesses/${businessId}/products`, { params: { search: search || undefined, type: type === "all" ? undefined : type, page, per_page: 20 } })).data, placeholderData: keepPreviousData });
  const archive = useMutation({ mutationFn: async (id: number) => api.delete(`/businesses/${businessId}/products/${id}`), onSuccess: async () => { await queryClient.invalidateQueries({ queryKey: ["products", businessId] }); toast.success("Item archived"); }, onError: (error) => toast.error("Could not archive item", { description: apiError(error) }) });
  if (!business) return <PageLoading />;
  if (query.isError) return <ErrorState onRetry={() => void query.refetch()} />;
  const rows = query.data?.data ?? [];
  return (
    <div className="space-y-7">
      <PageHeader eyebrow={business.name} title="Products & services" description="One flexible catalog for physical products, services and digital subscriptions." actions={canManage ? <Button leftIcon={<Plus size={17} />} onClick={() => setOpen(true)}>Add item</Button> : undefined} />
      <Card className="overflow-hidden">
        <div className="flex flex-col gap-3 border-b border-[var(--line)] p-4 sm:flex-row"><label className="relative flex-1"><span className="sr-only">Search catalog</span><Search size={17} className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--ink-soft)]" /><Input className="pl-10" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder="Search item name or SKU" /></label><Select className="sm:w-52" value={type} onChange={(event) => { setType(event.target.value); setPage(1); }}><option value="all">All item types</option><option value="product">Physical products</option><option value="service">Services</option><option value="digital_subscription">Digital subscriptions</option></Select></div>
        {query.isLoading ? <TableLoading /> : rows.length ? <>
          <div className="hidden overflow-x-auto md:block"><table className="w-full min-w-[850px] text-left text-sm"><thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-5 py-3 font-bold">Item</th><th className="px-4 py-3 font-bold">Type</th><th className="px-4 py-3 text-right font-bold">Selling price</th><th className="px-4 py-3 text-right font-bold">Direct cost</th><th className="px-4 py-3 text-right font-bold">Margin</th><th className="px-4 py-3 font-bold">Stock</th><th className="px-5 py-3 text-right font-bold">Actions</th></tr></thead><tbody className="divide-y divide-[var(--line)]">{rows.map((product) => { const margin = product.sale_price ? ((product.sale_price - product.cost_price) / product.sale_price) * 100 : 0; return <tr key={product.id} className="hover:bg-[var(--surface-soft)]"><td className="px-5 py-3.5"><div className="flex items-center gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]"><PackageCheck size={18} /></span><div><p className="font-bold">{product.name}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{product.sku || "No SKU"} · per {product.unit}</p></div></div></td><td className="px-4 py-3.5"><Badge tone={product.type === "digital_subscription" ? "info" : product.type === "product" ? "warning" : "success"}>{humanize(product.type)}</Badge></td><td className="px-4 py-3.5 text-right font-black">{money(product.sale_price, business.currency)}</td><td className="px-4 py-3.5 text-right text-[var(--ink-soft)]">{money(product.cost_price, business.currency)}</td><td className="px-4 py-3.5 text-right font-bold">{number(margin, 1)}%</td><td className="px-4 py-3.5">{product.track_inventory ? <span className={product.stock_quantity <= product.reorder_level ? "font-bold text-[var(--danger)]" : "font-semibold"}>{number(product.stock_quantity, product.stock_quantity % 1 ? 2 : 0)} {product.unit}</span> : <span className="text-[var(--ink-soft)]">Not tracked</span>}</td><td className="px-5 py-3.5"><div className="flex justify-end gap-1">{canManage ? <><button type="button" onClick={() => setEditing(product)} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--brand-soft)] hover:text-[var(--brand)]" aria-label="Edit item"><Edit3 size={15} /></button><button type="button" onClick={() => { if (window.confirm("Archive this item? Past invoices remain unchanged.")) archive.mutate(product.id); }} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--danger-soft)] hover:text-[var(--danger)]" aria-label="Archive item"><Trash2 size={15} /></button></> : null}</div></td></tr>; })}</tbody></table></div>
          <div className="grid gap-3 p-4 sm:grid-cols-2 md:hidden">{rows.map((product) => <button type="button" key={product.id} disabled={!canManage} onClick={() => setEditing(product)} className="rounded-2xl border border-[var(--line)] bg-white p-4 text-left enabled:transition enabled:hover:border-[#b3c3b6] enabled:hover:bg-[var(--surface-soft)]"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="truncate font-bold">{product.name}</p><p className="mt-1 text-xs text-[var(--ink-soft)]">{product.sku || humanize(product.type)}</p></div><Badge tone={product.active ? "success" : "neutral"}>{product.active ? "Active" : "Inactive"}</Badge></div><div className="mt-4 flex items-end justify-between gap-3"><div><p className="text-[10px] font-bold uppercase tracking-[0.1em] text-[var(--ink-soft)]">Direct cost</p><p className="mt-1 text-sm font-semibold">{money(product.cost_price, business.currency)}</p></div><p className="text-lg font-black">{money(product.sale_price, business.currency)}</p></div></button>)}</div>
          <Pager current={query.data?.meta.current_page ?? 1} last={query.data?.meta.last_page ?? 1} onChange={setPage} />
        </> : <EmptyState icon={Boxes} title={search || type !== "all" ? "No matching items" : "Build your catalog"} description={search || type !== "all" ? "Try a broader search or another item type." : "Saving both selling price and direct cost makes every sale immediately useful for profit tracking."} action={canManage ? <Button leftIcon={<Plus size={16} />} onClick={() => setOpen(true)}>Add first item</Button> : undefined} />}
      </Card>
      <ProductFormModal businessId={businessId} open={canManage && (open || Boolean(editing))} product={editing} defaultTax={business.default_tax_rate} onClose={() => { setOpen(false); setEditing(null); }} />
    </div>
  );
}
function Pager({ current, last, onChange }: { current: number; last: number; onChange: (page: number) => void }) { if (last <= 1) return null; return <div className="flex items-center justify-between border-t border-[var(--line)] p-3"><Button size="sm" variant="secondary" disabled={current <= 1} onClick={() => onChange(current - 1)}>Previous</Button><span className="text-xs text-[var(--ink-soft)]">Page {current} of {last}</span><Button size="sm" variant="secondary" disabled={current >= last} onClick={() => onChange(current + 1)}>Next</Button></div>; }
