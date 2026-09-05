"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { ApiMessage, Product } from "@/lib/types";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";

const blank = { sku: "", name: "", type: "service", unit: "unit", sale_price: "", cost_price: "", tax_rate: "0", track_inventory: false, stock_quantity: "0", reorder_level: "0", active: true, allows_multiple_writers: false };

export function ProductFormModal({ businessId, open, onClose, product, defaultTax = 0 }: { businessId: string | number; open: boolean; onClose: () => void; product?: Product | null; defaultTax?: number }) {
  const { getBusiness } = useBusinesses();
  const business = getBusiness(businessId);
  const writersEnabled = Boolean(business?.is_installment);
  const productType = business?.product_type ?? null;
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      setForm(product ? {
        sku: product.sku ?? "",
        name: product.name,
        type: product.type,
        unit: product.unit,
        sale_price: String(product.sale_price),
        cost_price: String(product.cost_price),
        tax_rate: String(product.tax_rate),
        track_inventory: product.track_inventory,
        stock_quantity: String(product.stock_quantity),
        reorder_level: String(product.reorder_level),
        active: product.active,
        allows_multiple_writers: product.allows_multiple_writers,
      } : { ...blank, tax_rate: String(defaultTax) });
      setErrors({});
    }
  }, [defaultTax, open, product]);

  const forcedTrackInventory = productType === "physical" && form.type === "product"
    ? true
    : productType === "digital"
      ? false
      : form.track_inventory;

  const mutation = useMutation({
    mutationFn: async () => {
      const payload = {
        ...form,
        sku: form.sku || null,
        sale_price: Number(form.sale_price),
        cost_price: Number(form.cost_price),
        tax_rate: Number(form.tax_rate || 0),
        track_inventory: forcedTrackInventory,
        stock_quantity: Number(form.stock_quantity || 0),
        reorder_level: Number(form.reorder_level || 0),
      };
      const path = product ? `/businesses/${businessId}/products/${product.id}` : `/businesses/${businessId}/products`;
      return (product ? await api.put<ApiMessage<{ product: Product }>>(path, payload) : await api.post<ApiMessage<{ product: Product }>>(path, payload)).data;
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["products", String(businessId)] });
      toast.success(product ? "Item updated" : "Item added");
      onClose();
    },
    onError: (error) => {
      setErrors(fieldErrors(error));
      toast.error("Could not save item", { description: apiError(error) });
    },
  });

  function update<K extends keyof typeof form>(key: K, value: (typeof form)[K]) { setForm((current) => ({ ...current, [key]: value })); }

  return (
    <Modal open={open} onClose={onClose} title={product ? "Edit item" : "Add a product or service"} description="Save the selling price and direct cost so profit is calculated correctly." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="product-form" loading={mutation.isPending}>Save item</Button></>} size="lg">
      <form id="product-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }} className="grid gap-4 sm:grid-cols-2">
        <FieldShell label="Name" htmlFor="product-name" error={errors.name?.[0]} required className="sm:col-span-2"><Input id="product-name" value={form.name} onChange={(event) => update("name", event.target.value)} placeholder="e.g. AI Pro — Monthly" autoFocus required /></FieldShell>
        <FieldShell label="Type" htmlFor="product-type" error={errors.type?.[0]} required><Select id="product-type" value={form.type} onChange={(event) => { const type = event.target.value; update("type", type); if (type !== "product") update("track_inventory", false); }}><option value="product">Physical product</option><option value="service">Service</option><option value="digital_subscription">Digital subscription</option></Select></FieldShell>
        <FieldShell label="SKU or code" htmlFor="sku" error={errors.sku?.[0]} hint="Optional"><Input id="sku" value={form.sku} onChange={(event) => update("sku", event.target.value.toUpperCase())} placeholder="e.g. AI-MONTH" /></FieldShell>
        <FieldShell label="Selling price" htmlFor="sale-price" error={errors.sale_price?.[0]} required><Input id="sale-price" type="number" min="0" step="0.01" placeholder="0.00" value={form.sale_price} onChange={(event) => update("sale_price", event.target.value)} required /></FieldShell>
        <FieldShell label="Direct cost" htmlFor="cost-price" error={errors.cost_price?.[0]} hint="Supplier or delivery cost" required><Input id="cost-price" type="number" min="0" step="0.01" placeholder="0.00" value={form.cost_price} onChange={(event) => update("cost_price", event.target.value)} required /></FieldShell>
        <FieldShell label="Unit" htmlFor="unit" error={errors.unit?.[0]}><Input id="unit" value={form.unit} onChange={(event) => update("unit", event.target.value)} placeholder="unit, project, month, seat" /></FieldShell>
        <FieldShell label="Tax rate" htmlFor="item-tax" error={errors.tax_rate?.[0]} hint="Percentage"><Input id="item-tax" type="number" min="0" max="100" step="0.01" placeholder="e.g. 13" value={form.tax_rate} onChange={(event) => update("tax_rate", event.target.value)} /></FieldShell>
        {form.type === "product" && productType !== "digital" ? (
          <div className="sm:col-span-2 rounded-2xl border border-[var(--line)] bg-[var(--surface-soft)] p-4">
            {productType === "physical" ? (
              <p className="text-sm font-bold">Stock tracking<span className="ml-2 text-xs font-medium text-[var(--ink-soft)]">Required for this physical-product business</span></p>
            ) : (
              <label className="flex items-center gap-2.5 text-sm font-bold"><input type="checkbox" checked={form.track_inventory} onChange={(event) => update("track_inventory", event.target.checked)} className="h-4 w-4 accent-[var(--brand)]" />Track available stock</label>
            )}
            {forcedTrackInventory ? <div className="mt-4 grid gap-4 sm:grid-cols-2"><FieldShell label="Current quantity" htmlFor="stock"><Input id="stock" type="number" min="0" step="0.001" placeholder="0" value={form.stock_quantity} onChange={(event) => update("stock_quantity", event.target.value)} /></FieldShell><FieldShell label="Low-stock alert at" htmlFor="reorder"><Input id="reorder" type="number" min="0" step="0.001" placeholder="0" value={form.reorder_level} onChange={(event) => update("reorder_level", event.target.value)} /></FieldShell></div> : null}
          </div>
        ) : null}
        {form.type === "service" && writersEnabled ? (
          <div className="sm:col-span-2 rounded-2xl border border-[var(--line)] bg-[var(--surface-soft)] p-4">
            <label className="flex items-center gap-2.5 text-sm font-bold"><input type="checkbox" checked={form.allows_multiple_writers} onChange={(event) => update("allows_multiple_writers", event.target.checked)} className="h-4 w-4 accent-[var(--brand)]" />Allow multiple writers</label>
            <p className="mt-1.5 text-xs leading-5 text-[var(--ink-soft)]">By default only one writer can be assigned to an invoice line for this service. Turn this on for team-based work.</p>
          </div>
        ) : null}
        {product ? <label className="flex items-center gap-2.5 text-sm font-semibold sm:col-span-2"><input type="checkbox" checked={form.active} onChange={(event) => update("active", event.target.checked)} className="h-4 w-4 accent-[var(--brand)]" />Available for new sales</label> : null}
      </form>
    </Modal>
  );
}
