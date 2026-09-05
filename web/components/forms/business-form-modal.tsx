"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, Business } from "@/lib/types";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ChevronDown, ChevronUp } from "lucide-react";
import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";
import { toast } from "sonner";

const initial = {
  name: "",
  code: "",
  category: "standard",
  product_type: "digital",
  business_type: "service",
  currency: "NPR",
  ownership_percent: "100",
  profit_share_percent: "100",
  pan_number: "",
  vat_number: "",
  phone: "",
  email: "",
  address: "",
  invoice_prefix: "",
  default_tax_rate: "0",
};

export function BusinessFormModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [form, setForm] = useState(initial);
  const [advanced, setAdvanced] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();
  const router = useRouter();

  useEffect(() => {
    if (!open) {
      setForm(initial);
      setAdvanced(false);
      setErrors({});
    }
  }, [open]);

  const mutation = useMutation({
    mutationFn: async () => {
      const isInstallment = form.category === "installment";
      const payload = {
        ...form,
        business_type: isInstallment ? "service" : form.business_type,
        product_type: isInstallment ? undefined : form.product_type,
        code: form.code || undefined,
        invoice_prefix: form.invoice_prefix || undefined,
        pan_number: form.pan_number || undefined,
        vat_number: form.vat_number || undefined,
        phone: form.phone || undefined,
        email: form.email || undefined,
        address: form.address || undefined,
        ownership_percent: Number(form.ownership_percent),
        profit_share_percent: Number(form.profit_share_percent || form.ownership_percent),
        default_tax_rate: Number(form.default_tax_rate || 0),
      };
      return (await api.post<ApiMessage<{ business: Business }>>("/businesses", payload)).data;
    },
    onSuccess: async (response) => {
      await queryClient.invalidateQueries({ queryKey: ["businesses"] });
      await queryClient.invalidateQueries({ queryKey: ["portfolio-dashboard"] });
      toast.success("Business added", { description: `${response.business.name} now has its own secure workspace.` });
      onClose();
      router.push(`/b/${response.business.id}`);
    },
    onError: (error) => {
      setErrors(fieldErrors(error));
      toast.error("Could not add business", { description: apiError(error) });
    },
  });

  function update(key: keyof typeof form, value: string) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    setErrors({});
    mutation.mutate();
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Add a business"
      description="Start with the essentials. Tax and invoice details can be completed now or later."
      size="lg"
      footer={<><Button type="button" variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="business-form" loading={mutation.isPending}>Create business</Button></>}
    >
      <form id="business-form" onSubmit={submit} className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2">
          <FieldShell label="Business name" htmlFor="business-name" error={errors.name?.[0]} required className="sm:col-span-2"><Input id="business-name" value={form.name} onChange={(event) => update("name", event.target.value)} placeholder="e.g. Aimers AI" autoFocus required /></FieldShell>
          <FieldShell label="Category" htmlFor="business-category" error={errors.category?.[0]} hint="Sets how this business works — can't be changed later" required className="sm:col-span-2">
            <Select id="business-category" value={form.category} onChange={(event) => update("category", event.target.value)}>
              <option value="standard">Normal business — sales, invoices and a catalog</option>
              <option value="installment">Installment / project-based — clients, writers and staged payments</option>
            </Select>
          </FieldShell>
          {form.category === "standard" ? (
            <>
              <FieldShell label="Business model" htmlFor="business-type" error={errors.business_type?.[0]} required><Select id="business-type" value={form.business_type} onChange={(event) => update("business_type", event.target.value)}><option value="service">Services</option><option value="product">Physical products</option><option value="digital_subscription">Digital subscriptions</option><option value="mixed">Mixed business</option></Select></FieldShell>
              <FieldShell label="Product type" htmlFor="product-type" error={errors.product_type?.[0]} hint="Sets whether the catalog tracks stock" required>
                <Select id="product-type" value={form.product_type} onChange={(event) => update("product_type", event.target.value)}>
                  <option value="digital">Digital products — no stock tracking</option>
                  <option value="physical">Physical products — track stock</option>
                </Select>
              </FieldShell>
            </>
          ) : null}
          <FieldShell label="Currency" htmlFor="currency" error={errors.currency?.[0]}><Select id="currency" value={form.currency} onChange={(event) => update("currency", event.target.value)}><option value="NPR">NPR — Nepalese Rupee</option><option value="USD">USD — US Dollar</option><option value="INR">INR — Indian Rupee</option></Select></FieldShell>
          <FieldShell label="Your ownership" htmlFor="ownership" error={errors.ownership_percent?.[0]} hint="Percentage" required><Input id="ownership" type="number" min="0" max="100" step="0.0001" placeholder="e.g. 40" value={form.ownership_percent} onChange={(event) => { update("ownership_percent", event.target.value); if (form.profit_share_percent === form.ownership_percent) update("profit_share_percent", event.target.value); }} required /></FieldShell>
          <FieldShell label="Your profit share" htmlFor="profit-share" error={errors.profit_share_percent?.[0]} hint="Usually same as ownership"><Input id="profit-share" type="number" min="0" max="100" step="0.0001" placeholder="e.g. 40" value={form.profit_share_percent} onChange={(event) => update("profit_share_percent", event.target.value)} /></FieldShell>
        </div>

        <button type="button" onClick={() => setAdvanced((current) => !current)} className="flex w-full items-center justify-between rounded-xl border border-dashed border-[var(--line-strong)] bg-[var(--surface-soft)] px-4 py-3 text-left text-sm font-bold text-[var(--ink)]">
          Invoice, tax and contact details
          {advanced ? <ChevronUp size={17} /> : <ChevronDown size={17} />}
        </button>

        {advanced ? (
          <div className="grid gap-4 sm:grid-cols-2">
            <FieldShell label="Short code" htmlFor="code" error={errors.code?.[0]} hint="Auto if blank"><Input id="code" value={form.code} onChange={(event) => update("code", event.target.value.toUpperCase())} maxLength={12} placeholder="AAI" /></FieldShell>
            <FieldShell label="Invoice prefix" htmlFor="invoice-prefix" error={errors.invoice_prefix?.[0]} hint="Auto if blank"><Input id="invoice-prefix" value={form.invoice_prefix} onChange={(event) => update("invoice_prefix", event.target.value.toUpperCase())} maxLength={12} placeholder="INV" /></FieldShell>
            <FieldShell label="PAN number" htmlFor="pan" error={errors.pan_number?.[0]}><Input id="pan" value={form.pan_number} onChange={(event) => update("pan_number", event.target.value)} placeholder="e.g. 301234567" /></FieldShell>
            <FieldShell label="VAT number" htmlFor="vat" error={errors.vat_number?.[0]}><Input id="vat" value={form.vat_number} onChange={(event) => update("vat_number", event.target.value)} placeholder="e.g. 600123456" /></FieldShell>
            <FieldShell label="Default tax rate" htmlFor="tax" error={errors.default_tax_rate?.[0]} hint="Percentage"><Input id="tax" type="number" min="0" max="100" step="0.01" placeholder="e.g. 13" value={form.default_tax_rate} onChange={(event) => update("default_tax_rate", event.target.value)} /></FieldShell>
            <FieldShell label="Phone" htmlFor="business-phone" error={errors.phone?.[0]}><Input id="business-phone" value={form.phone} onChange={(event) => update("phone", event.target.value)} placeholder="98XXXXXXXX" /></FieldShell>
            <FieldShell label="Billing email" htmlFor="business-email" error={errors.email?.[0]} className="sm:col-span-2"><Input id="business-email" type="email" value={form.email} onChange={(event) => update("email", event.target.value)} placeholder="billing@company.com" /></FieldShell>
            <FieldShell label="Address" htmlFor="business-address" error={errors.address?.[0]} className="sm:col-span-2"><Textarea id="business-address" value={form.address} onChange={(event) => update("address", event.target.value)} placeholder="Street, city, ward or landmark" /></FieldShell>
          </div>
        ) : null}
      </form>
    </Modal>
  );
}
