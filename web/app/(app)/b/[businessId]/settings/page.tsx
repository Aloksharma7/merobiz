"use client";

import { OwnershipFormModal } from "@/components/forms/ownership-form-modal";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { ErrorState } from "@/components/ui/error-state";
import { PageLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api, apiError, fieldErrors } from "@/lib/api";
import { readableTextColor } from "@/lib/branding";
import { useBusinesses } from "@/lib/business-context";
import type { ApiMessage, Business, BusinessType, Member, OwnershipRecord } from "@/lib/types";
import { number, prettyDate } from "@/lib/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, Building2, CalendarClock, Eye, FileText, Landmark, LayoutDashboard, Palette, Percent, Plus, Save, ShieldCheck, SlidersHorizontal, ToggleLeft, Trash2, Upload, UsersRound } from "lucide-react";
import { useParams, useRouter } from "next/navigation";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";

type FormState = {
  name: string;
  code: string;
  business_type: BusinessType;
  currency: string;
  pan_number: string;
  vat_number: string;
  phone: string;
  email: string;
  address: string;
  invoice_prefix: string;
  default_tax_rate: string;
  feature_installments: boolean;
  dash_overview_cards: boolean;
  dash_profit_breakdown: boolean;
  dash_quick_actions: boolean;
  dash_performance_trend: boolean;
  dash_top_products: boolean;
  dash_recent_invoices: boolean;
  dash_expense_mix: boolean;
  brand_tagline: string;
  brand_primary_color: string;
  brand_nav_color: string;
  brand_accent_color: string;
  show_logo_workspace: boolean;
  show_logo_invoice: boolean;
  website: string;
  company_name: string;
  invoice_terms: string;
  invoice_note: string;
  bank_name: string;
  bank_account_name: string;
  bank_account_number: string;
  bank_branch: string;
  authorized_name: string;
  authorized_title: string;
  show_seller_address: boolean;
  show_seller_phone: boolean;
  show_seller_email: boolean;
  show_seller_website: boolean;
  show_seller_pan: boolean;
  show_customer_address: boolean;
  show_customer_phone: boolean;
  show_customer_email: boolean;
  show_customer_pan: boolean;
  show_due_date: boolean;
  show_payment_mode: boolean;
  show_prepared_by: boolean;
  show_status: boolean;
  show_item_discount: boolean;
  show_item_tax: boolean;
  show_discount_summary: boolean;
  show_tax_summary: boolean;
  show_amount_in_words: boolean;
  show_bank_details: boolean;
  show_payment_record: boolean;
  show_notes: boolean;
  show_terms: boolean;
  show_signature: boolean;
};

function toForm(business: Business): FormState {
  return {
    name: business.name,
    code: business.code,
    business_type: business.business_type,
    currency: business.currency,
    pan_number: business.pan_number ?? "",
    vat_number: business.vat_number ?? "",
    phone: business.phone ?? "",
    email: business.email ?? "",
    address: business.address ?? "",
    invoice_prefix: business.invoice_prefix,
    default_tax_rate: String(business.default_tax_rate),
    feature_installments: business.settings?.features?.installments ?? false,
    dash_overview_cards: business.settings?.dashboard?.show_overview_cards ?? true,
    dash_profit_breakdown: business.settings?.dashboard?.show_profit_breakdown ?? true,
    dash_quick_actions: business.settings?.dashboard?.show_quick_actions ?? true,
    dash_performance_trend: business.settings?.dashboard?.show_performance_trend ?? true,
    dash_top_products: business.settings?.dashboard?.show_top_products ?? true,
    dash_recent_invoices: business.settings?.dashboard?.show_recent_invoices ?? true,
    dash_expense_mix: business.settings?.dashboard?.show_expense_mix ?? true,
    brand_tagline: business.settings?.branding?.tagline ?? "",
    brand_primary_color: business.settings?.branding?.primary_color ?? "#135f48",
    brand_nav_color: business.settings?.branding?.nav_color ?? "#0b3c31",
    brand_accent_color: business.settings?.branding?.accent_color ?? "#eaa737",
    show_logo_workspace: business.settings?.branding?.show_logo_workspace ?? true,
    show_logo_invoice: business.settings?.branding?.show_logo_invoice ?? true,
    website: business.settings?.invoice?.website ?? "",
    company_name: business.settings?.invoice?.company_name ?? "",
    invoice_terms: business.settings?.invoice?.invoice_terms ?? "",
    invoice_note: business.settings?.invoice?.invoice_note ?? "",
    bank_name: business.settings?.invoice?.bank_name ?? "",
    bank_account_name: business.settings?.invoice?.bank_account_name ?? "",
    bank_account_number: business.settings?.invoice?.bank_account_number ?? "",
    bank_branch: business.settings?.invoice?.bank_branch ?? "",
    authorized_name: business.settings?.invoice?.authorized_name ?? "",
    authorized_title: business.settings?.invoice?.authorized_title ?? "",
    show_seller_address: business.settings?.invoice?.show_seller_address ?? true,
    show_seller_phone: business.settings?.invoice?.show_seller_phone ?? true,
    show_seller_email: business.settings?.invoice?.show_seller_email ?? true,
    show_seller_website: business.settings?.invoice?.show_seller_website ?? true,
    show_seller_pan: business.settings?.invoice?.show_seller_pan ?? true,
    show_customer_address: business.settings?.invoice?.show_customer_address ?? true,
    show_customer_phone: business.settings?.invoice?.show_customer_phone ?? true,
    show_customer_email: business.settings?.invoice?.show_customer_email ?? false,
    show_customer_pan: business.settings?.invoice?.show_customer_pan ?? false,
    show_due_date: business.settings?.invoice?.show_due_date ?? true,
    show_payment_mode: business.settings?.invoice?.show_payment_mode ?? true,
    show_prepared_by: business.settings?.invoice?.show_prepared_by ?? false,
    show_status: business.settings?.invoice?.show_status ?? false,
    show_item_discount: business.settings?.invoice?.show_item_discount ?? true,
    show_item_tax: business.settings?.invoice?.show_item_tax ?? true,
    show_discount_summary: business.settings?.invoice?.show_discount_summary ?? true,
    show_tax_summary: business.settings?.invoice?.show_tax_summary ?? true,
    show_amount_in_words: business.settings?.invoice?.show_amount_in_words ?? true,
    show_bank_details: business.settings?.invoice?.show_bank_details ?? false,
    show_payment_record: business.settings?.invoice?.show_payment_record ?? false,
    show_notes: business.settings?.invoice?.show_notes ?? true,
    show_terms: business.settings?.invoice?.show_terms ?? true,
    show_signature: business.settings?.invoice?.show_signature ?? true,
  };
}

export default function SettingsPage() {
  const { businessId } = useParams<{ businessId: string }>();
  const router = useRouter();
  const { getBusiness } = useBusinesses();
  const business = getBusiness(businessId);
  const queryClient = useQueryClient();
  const [form, setForm] = useState<FormState | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [ownershipOpen, setOwnershipOpen] = useState(false);
  const [logoPreview, setLogoPreview] = useState<string | null>(null);
  const [deleteConfirmation, setDeleteConfirmation] = useState("");
  const logoInputRef = useRef<HTMLInputElement>(null);
  const owner = business?.my_role === "owner";
  const canManageOwnership = business?.full_control ?? false;

  useEffect(() => { if (business) setForm(toForm(business)); }, [business]);

  const team = useQuery({
    queryKey: ["team", businessId, "settings"],
    queryFn: async () => (await api.get<{ data: Member[] }>(`/businesses/${businessId}/team`)).data.data,
    enabled: Boolean(business && owner),
  });
  const ownerships = useQuery({
    queryKey: ["ownerships", businessId],
    queryFn: async () => (await api.get<{ data: OwnershipRecord[] }>(`/businesses/${businessId}/ownerships`)).data.data,
    enabled: Boolean(business && owner),
  });

  const mutation = useMutation({
    mutationFn: async () => {
      if (!form) throw new Error("Business settings are not ready.");
      const {
        feature_installments,
        dash_overview_cards, dash_profit_breakdown, dash_quick_actions, dash_performance_trend, dash_top_products, dash_recent_invoices, dash_expense_mix,
        brand_tagline, brand_primary_color, brand_nav_color, brand_accent_color, show_logo_workspace, show_logo_invoice,
        website, company_name, invoice_terms, invoice_note, bank_name, bank_account_name, bank_account_number, bank_branch, authorized_name, authorized_title,
        show_seller_address, show_seller_phone, show_seller_email, show_seller_website, show_seller_pan,
        show_customer_address, show_customer_phone, show_customer_email, show_customer_pan,
        show_due_date, show_payment_mode, show_prepared_by, show_status, show_item_discount, show_item_tax,
        show_discount_summary, show_tax_summary, show_amount_in_words, show_bank_details, show_payment_record,
        show_notes, show_terms, show_signature,
        ...businessFields
      } = form;
      return (await api.patch<ApiMessage<{ business: Business }>>(`/businesses/${businessId}`, {
        ...businessFields,
        default_tax_rate: Number(form.default_tax_rate),
        pan_number: form.pan_number || null,
        vat_number: form.vat_number || null,
        phone: form.phone || null,
        email: form.email || null,
        address: form.address || null,
        settings: {
          features: {
            installments: feature_installments,
          },
          dashboard: {
            show_overview_cards: dash_overview_cards,
            show_profit_breakdown: dash_profit_breakdown,
            show_quick_actions: dash_quick_actions,
            show_performance_trend: dash_performance_trend,
            show_top_products: dash_top_products,
            show_recent_invoices: dash_recent_invoices,
            show_expense_mix: dash_expense_mix,
          },
          branding: {
            tagline: brand_tagline || null,
            primary_color: brand_primary_color,
            nav_color: brand_nav_color,
            accent_color: brand_accent_color,
            show_logo_workspace,
            show_logo_invoice,
          },
          invoice: {
            website: website || null,
            company_name: company_name || null,
            invoice_terms: invoice_terms || null,
            invoice_note: invoice_note || null,
            bank_name: bank_name || null,
            bank_account_name: bank_account_name || null,
            bank_account_number: bank_account_number || null,
            bank_branch: bank_branch || null,
            authorized_name: authorized_name || null,
            authorized_title: authorized_title || null,
            show_seller_address, show_seller_phone, show_seller_email, show_seller_website, show_seller_pan,
            show_customer_address, show_customer_phone, show_customer_email, show_customer_pan,
            show_due_date, show_payment_mode, show_prepared_by, show_status, show_item_discount, show_item_tax,
            show_discount_summary, show_tax_summary, show_amount_in_words, show_bank_details, show_payment_record,
            show_notes, show_terms, show_signature,
          },
        },
      })).data;
    },
    onSuccess: async () => {
      setErrors({});
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["businesses"] }),
        queryClient.invalidateQueries({ queryKey: ["business-dashboard", businessId] }),
        queryClient.invalidateQueries({ queryKey: ["portfolio-dashboard"] }),
      ]);
      toast.success("Business settings saved");
    },
    onError: (error) => {
      setErrors(fieldErrors(error));
      toast.error("Could not save settings", { description: apiError(error) });
    },
  });

  const logoMutation = useMutation({
    mutationFn: async (file: File) => {
      const payload = new FormData();
      payload.append("logo", file);
      return (await api.post<ApiMessage<{ business: Business }>>(`/businesses/${businessId}/branding/logo`, payload)).data;
    },
    onSuccess: async () => {
      setLogoPreview(null);
      if (logoInputRef.current) logoInputRef.current.value = "";
      await queryClient.invalidateQueries({ queryKey: ["businesses"] });
      toast.success("Business logo updated");
    },
    onError: (error) => {
      setLogoPreview(null);
      toast.error("Could not upload logo", { description: apiError(error) });
    },
  });

  const removeLogoMutation = useMutation({
    mutationFn: async () => (await api.delete<ApiMessage<{ business: Business }>>(`/businesses/${businessId}/branding/logo`)).data,
    onSuccess: async () => {
      setLogoPreview(null);
      await queryClient.invalidateQueries({ queryKey: ["businesses"] });
      toast.success("Business logo removed");
    },
    onError: (error) => toast.error("Could not remove logo", { description: apiError(error) }),
  });

  const deleteBusinessMutation = useMutation({
    mutationFn: async () => (await api.delete<ApiMessage>(`/businesses/${businessId}`)).data,
    onSuccess: async () => {
      toast.success("Business deleted");
      await queryClient.invalidateQueries({ queryKey: ["businesses"] });
      router.replace("/");
    },
    onError: (error) => toast.error("Could not delete business", { description: apiError(error) }),
  });

  const currentOwnerships = useMemo(() => ownerships.data?.filter((row) => row.current) ?? [], [ownerships.data]);
  const totalOwnership = currentOwnerships.reduce((sum, row) => sum + row.ownership_percent, 0);
  const totalProfitShare = currentOwnerships.reduce((sum, row) => sum + row.profit_share_percent, 0);

  if (!business || !form) return <PageLoading />;
  if (team.isError || ownerships.isError) return <ErrorState onRetry={() => void Promise.all([team.refetch(), ownerships.refetch()])} />;

  function update<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((current) => current ? { ...current, [key]: value } : current);
  }

  function chooseLogo(file?: File) {
    if (!file) return;
    if (!file.type.startsWith("image/")) {
      toast.error("Choose a JPG, PNG or WebP image.");
      return;
    }
    if (file.size > 3 * 1024 * 1024) {
      toast.error("Logo must be 3 MB or smaller.");
      return;
    }
    const preview = URL.createObjectURL(file);
    setLogoPreview((current) => { if (current?.startsWith("blob:")) URL.revokeObjectURL(current); return preview; });
    logoMutation.mutate(file);
  }

  return (
    <div className="space-y-7">
      <PageHeader
        eyebrow={business.name}
        title="Business settings"
        description="Keep routine setup concise; expand only the identity, billing and partner details that matter."
        actions={<Button type="submit" form="business-settings-form" leftIcon={<Save size={17} />} loading={mutation.isPending}>Save changes</Button>}
      />

      <form id="business-settings-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }} className="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(320px,0.75fr)]">
        <div className="space-y-4">
          <Card>
            <CardHeader title={<span className="flex items-center gap-2"><Building2 size={18} className="text-[var(--brand)]" />Business identity</span>} description="Shown across dashboards, invoices and reports." />
            <CardBody className="space-y-4">
              <div className="rounded-2xl bg-[var(--surface-soft)] px-4 py-3 text-xs leading-5 text-[var(--ink-soft)]">
                <span className="font-bold text-[var(--ink)]">{business.is_installment ? "Installment / project-based business" : "Standard business"}</span>
                {business.product_type ? <span> · {business.product_type === "physical" ? "Physical products (stock tracked)" : "Digital products (no stock tracking)"}</span> : null}
                <span className="block mt-0.5">Set when the business was created and can&apos;t be changed here.</span>
              </div>
              <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_150px]">
                <FieldShell label="Business name" htmlFor="business-name" error={errors.name?.[0]} required><Input id="business-name" value={form.name} onChange={(event) => update("name", event.target.value)} placeholder="e.g. Aimers AI" required /></FieldShell>
                <FieldShell label="Short code" htmlFor="business-code" hint="Max 12" error={errors.code?.[0]} required><Input id="business-code" value={form.code} onChange={(event) => update("code", event.target.value.toUpperCase())} maxLength={12} placeholder="e.g. AAI" required /></FieldShell>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <FieldShell label="Business model" htmlFor="business-type" error={errors.business_type?.[0]} required><Select id="business-type" value={form.business_type} onChange={(event) => update("business_type", event.target.value as BusinessType)}><option value="product">Physical products</option><option value="service">Services</option><option value="digital_subscription">Digital subscriptions</option><option value="mixed">Mixed business</option></Select></FieldShell>
                <FieldShell label="Currency" htmlFor="business-currency" error={errors.currency?.[0]} required><Input id="business-currency" value={form.currency} onChange={(event) => update("currency", event.target.value.toUpperCase())} maxLength={3} placeholder="NPR" required /></FieldShell>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <FieldShell label="Contact phone" htmlFor="business-phone" error={errors.phone?.[0]}><Input id="business-phone" value={form.phone} onChange={(event) => update("phone", event.target.value)} placeholder="98XXXXXXXX" /></FieldShell>
                <FieldShell label="Contact email" htmlFor="business-email" error={errors.email?.[0]}><Input id="business-email" type="email" value={form.email} onChange={(event) => update("email", event.target.value)} placeholder="billing@company.com" /></FieldShell>
              </div>
              <FieldShell label="Business address" htmlFor="business-address" error={errors.address?.[0]}><Textarea id="business-address" className="min-h-20" value={form.address} onChange={(event) => update("address", event.target.value)} placeholder="Street, city, ward or landmark" /></FieldShell>
            </CardBody>
          </Card>

          <Card>
            <CardHeader title={<span className="flex items-center gap-2"><ToggleLeft size={18} className="text-[var(--brand)]" />Workflow features</span>} description="Turn on only what this specific business needs. Off by default for a simple, uncluttered workflow." />
            <CardBody className="space-y-3">
              <label className="flex items-start gap-3 rounded-2xl border border-[var(--line)] p-4">
                <input type="checkbox" checked={form.feature_installments} onChange={(event) => update("feature_installments", event.target.checked)} className="mt-0.5 h-4 w-4 accent-[var(--brand)]" />
                <span>
                  <span className="block text-sm font-bold">Installment plans</span>
                  <span className="mt-0.5 block text-xs leading-5 text-[var(--ink-soft)]">Allow a sale to be split into a down payment and scheduled later payments instead of one full amount. Leave off for simple full/partial payments.</span>
                </span>
              </label>
            </CardBody>
          </Card>

          <Card>
            <CardHeader title={<span className="flex items-center gap-2"><LayoutDashboard size={18} className="text-[var(--brand)]" />Dashboard layout</span>} description="Choose which sections appear on this business's dashboard. The owner profile and today/month figures at the top are always shown." />
            <CardBody className="space-y-3">
              {([
                ["dash_overview_cards", "Overview cards", "The profit, net sales, cash collected and receivables cards for the selected date range."],
                ["dash_profit_breakdown", "Profit breakdown", "Gross profit, expenses and net profit row (owners and admins only)."],
                ["dash_quick_actions", "Quick actions", "Shortcut tiles for new sale, add expense, add customer and add item."],
                ["dash_performance_trend", "Performance trend", "The sales and profit chart over the last six months."],
                ["dash_top_products", "Top products & services", "Best-selling items ranked by net item sales."],
                ["dash_recent_invoices", "Recent invoices", "The latest sales activity table."],
                ["dash_expense_mix", "Expense mix", "Approved costs by category (or the profit-share panel for employees)."],
              ] as const).map(([key, label, description]) => (
                <label key={key} className="flex items-start gap-3 rounded-2xl border border-[var(--line)] p-4">
                  <input type="checkbox" checked={form[key]} onChange={(event) => update(key, event.target.checked)} className="mt-0.5 h-4 w-4 accent-[var(--brand)]" />
                  <span>
                    <span className="block text-sm font-bold">{label}</span>
                    <span className="mt-0.5 block text-xs leading-5 text-[var(--ink-soft)]">{description}</span>
                  </span>
                </label>
              ))}
            </CardBody>
          </Card>

          <Card>
            <CardHeader title={<span className="flex items-center gap-2"><Palette size={18} className="text-[var(--brand)]" />Business branding</span>} description="This identity is inherited by the staff workspace and customer invoice for this business only." />
            <CardBody className="space-y-5">
              <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                <div className="space-y-4">
                  <FieldShell label="Brand tagline" htmlFor="brand-tagline" hint="Optional" error={errors["settings.branding.tagline"]?.[0]}>
                    <Input id="brand-tagline" value={form.brand_tagline} onChange={(event) => update("brand_tagline", event.target.value)} placeholder="e.g. Learn. Build. Grow." maxLength={160} />
                  </FieldShell>
                  <div className="grid gap-3 sm:grid-cols-3">
                    <BrandColorField label="Primary" value={form.brand_primary_color} onChange={(value) => update("brand_primary_color", value)} error={errors["settings.branding.primary_color"]?.[0]} />
                    <BrandColorField label="Navigation" value={form.brand_nav_color} onChange={(value) => update("brand_nav_color", value)} error={errors["settings.branding.nav_color"]?.[0]} />
                    <BrandColorField label="Accent" value={form.brand_accent_color} onChange={(value) => update("brand_accent_color", value)} error={errors["settings.branding.accent_color"]?.[0]} />
                  </div>
                </div>

                <div className="rounded-2xl border border-[var(--line)] bg-[var(--surface-soft)] p-4">
                  <p className="text-[10px] font-black uppercase tracking-[0.13em] text-[var(--ink-soft)]">Live identity preview</p>
                  <div className="mt-3 overflow-hidden rounded-2xl border border-black/5 shadow-sm" style={{ backgroundColor: form.brand_nav_color }}>
                    <div className="flex items-center gap-3 p-4" style={{ color: /^#[0-9A-Fa-f]{6}$/.test(form.brand_nav_color) ? readableTextColor(form.brand_nav_color) : "#ffffff" }}>
                      <span className="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-white/95 p-1.5 text-xs font-black" style={{ color: form.brand_nav_color }}>
                        {(logoPreview || business.settings?.branding?.logo_url) ? <img src={logoPreview || business.settings?.branding?.logo_url || ""} alt="Logo preview" className="h-full w-full object-contain" /> : business.code.slice(0, 4)}
                      </span>
                      <div className="min-w-0"><p className="truncate font-black">{form.name || business.name}</p><p className="mt-0.5 truncate text-xs opacity-60">{form.brand_tagline || "Business workspace"}</p></div>
                    </div>
                    <div className="h-1.5" style={{ backgroundColor: form.brand_accent_color }} />
                  </div>
                </div>
              </div>

              <div className="flex flex-wrap items-center gap-2">
                <input ref={logoInputRef} type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={(event) => chooseLogo(event.target.files?.[0])} />
                <Button type="button" variant="secondary" size="sm" leftIcon={<Upload size={15} />} loading={logoMutation.isPending} onClick={() => logoInputRef.current?.click()}>Upload logo</Button>
                {business.settings?.branding?.logo_url || logoPreview ? <Button type="button" variant="ghost" size="sm" leftIcon={<Trash2 size={15} />} loading={removeLogoMutation.isPending} onClick={() => removeLogoMutation.mutate()}>Remove logo</Button> : null}
                <span className="text-xs text-[var(--ink-soft)]">PNG, JPG or WebP · max 3 MB · square/transparent works best.</span>
              </div>

              <div className="grid gap-2 sm:grid-cols-2">
                <InvoiceToggle label="Show logo in staff workspace" checked={form.show_logo_workspace} onChange={(value) => update("show_logo_workspace", value)} />
                <InvoiceToggle label="Show logo on customer invoice" checked={form.show_logo_invoice} onChange={(value) => update("show_logo_invoice", value)} />
              </div>
            </CardBody>
          </Card>

          <Card>
            <CardHeader title={<span className="flex items-center gap-2"><FileText size={18} className="text-[var(--brand)]" />Invoice defaults</span>} description="Applied automatically when staff create a sale; they can focus on the customer and items." />
            <CardBody className="space-y-4">
              <FieldShell label="Company name on invoice" htmlFor="invoice-company-name" hint="Shown as the seller name on printed invoices. Leave blank to use the business name." error={errors["settings.invoice.company_name"]?.[0]}><Input id="invoice-company-name" value={form.company_name} onChange={(event) => update("company_name", event.target.value)} placeholder={business.name} /></FieldShell>
              <div className="grid gap-4 sm:grid-cols-2">
                <FieldShell label="Default tax rate" htmlFor="default-tax" hint="Percent" error={errors.default_tax_rate?.[0]} required><Input id="default-tax" type="number" min="0" max="100" step="0.01" placeholder="e.g. 13" value={form.default_tax_rate} onChange={(event) => update("default_tax_rate", event.target.value)} required /></FieldShell>
                <FieldShell label="PAN number" htmlFor="pan-number" hint="Businesses sharing this PAN share one invoice sequence" error={errors.pan_number?.[0]}><Input id="pan-number" value={form.pan_number} onChange={(event) => update("pan_number", event.target.value)} placeholder="e.g. 301234567" /></FieldShell>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <FieldShell label="VAT number" htmlFor="vat-number" error={errors.vat_number?.[0]}><Input id="vat-number" value={form.vat_number} onChange={(event) => update("vat_number", event.target.value)} placeholder="e.g. 600123456" /></FieldShell>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <FieldShell label="Website" htmlFor="invoice-website" error={errors["settings.invoice.website"]?.[0]}><Input id="invoice-website" value={form.website} onChange={(event) => update("website", event.target.value)} placeholder="www.example.com" /></FieldShell>
                <FieldShell label="Authorized person" htmlFor="authorized-name" error={errors["settings.invoice.authorized_name"]?.[0]}><Input id="authorized-name" value={form.authorized_name} onChange={(event) => update("authorized_name", event.target.value)} placeholder="Name for invoice signature" /></FieldShell>
              </div>
              <FieldShell label="Authorized title" htmlFor="authorized-title" error={errors["settings.invoice.authorized_title"]?.[0]}><Input id="authorized-title" value={form.authorized_title} onChange={(event) => update("authorized_title", event.target.value)} placeholder="e.g. Authorized Signatory" /></FieldShell>
              <div className="grid gap-4 sm:grid-cols-2">
                <FieldShell label="Bank name" htmlFor="bank-name" error={errors["settings.invoice.bank_name"]?.[0]}><Input id="bank-name" value={form.bank_name} onChange={(event) => update("bank_name", event.target.value)} placeholder="e.g. Nabil Bank" /></FieldShell>
                <FieldShell label="Bank branch" htmlFor="bank-branch" error={errors["settings.invoice.bank_branch"]?.[0]}><Input id="bank-branch" value={form.bank_branch} onChange={(event) => update("bank_branch", event.target.value)} placeholder="e.g. New Road" /></FieldShell>
                <FieldShell label="Account name" htmlFor="bank-account-name" error={errors["settings.invoice.bank_account_name"]?.[0]}><Input id="bank-account-name" value={form.bank_account_name} onChange={(event) => update("bank_account_name", event.target.value)} placeholder="Account holder name" /></FieldShell>
                <FieldShell label="Account number" htmlFor="bank-account-number" error={errors["settings.invoice.bank_account_number"]?.[0]}><Input id="bank-account-number" value={form.bank_account_number} onChange={(event) => update("bank_account_number", event.target.value)} placeholder="Account number" /></FieldShell>
              </div>
              <FieldShell label="Invoice note" htmlFor="invoice-note" hint="Short note shown near totals" error={errors["settings.invoice.invoice_note"]?.[0]}><Textarea id="invoice-note" className="min-h-20" value={form.invoice_note} onChange={(event) => update("invoice_note", event.target.value)} placeholder="Thank you for your business." /></FieldShell>
              <FieldShell label="Terms & conditions" htmlFor="invoice-terms" hint="Printed at the bottom of customer invoices" error={errors["settings.invoice.invoice_terms"]?.[0]}><Textarea id="invoice-terms" className="min-h-24" value={form.invoice_terms} onChange={(event) => update("invoice_terms", event.target.value)} placeholder="Payment due within 7 days. Goods/services once accepted are subject to the agreed return/refund policy." /></FieldShell>

              <div className="rounded-2xl border border-[var(--line)] bg-[var(--surface-soft)] p-4 sm:p-5">
                <div className="flex items-start gap-3">
                  <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white text-[var(--brand)] shadow-sm"><Eye size={17} /></span>
                  <div>
                    <p className="text-sm font-black">Invoice field visibility</p>
                    <p className="mt-0.5 text-xs leading-5 text-[var(--ink-soft)]">Owner/Admin chooses what customers see. Core fields—business name, invoice number/date, customer name, line items and grand total—always remain visible.</p>
                  </div>
                </div>

                <div className="mt-5 space-y-5">
                  <ToggleGroup title="Seller details">
                    <InvoiceToggle label="Address" checked={form.show_seller_address} onChange={(value) => update("show_seller_address", value)} />
                    <InvoiceToggle label="Phone" checked={form.show_seller_phone} onChange={(value) => update("show_seller_phone", value)} />
                    <InvoiceToggle label="Email" checked={form.show_seller_email} onChange={(value) => update("show_seller_email", value)} />
                    <InvoiceToggle label="Website" checked={form.show_seller_website} onChange={(value) => update("show_seller_website", value)} />
                    <InvoiceToggle label="Seller PAN" checked={form.show_seller_pan} onChange={(value) => update("show_seller_pan", value)} />
                  </ToggleGroup>

                  <ToggleGroup title="Customer details">
                    <InvoiceToggle label="Address" checked={form.show_customer_address} onChange={(value) => update("show_customer_address", value)} />
                    <InvoiceToggle label="Phone" checked={form.show_customer_phone} onChange={(value) => update("show_customer_phone", value)} />
                    <InvoiceToggle label="Email" checked={form.show_customer_email} onChange={(value) => update("show_customer_email", value)} />
                    <InvoiceToggle label="Buyer PAN" description="Off by default" checked={form.show_customer_pan} onChange={(value) => update("show_customer_pan", value)} />
                  </ToggleGroup>

                  <ToggleGroup title="Invoice details">
                    <InvoiceToggle label="Due date" checked={form.show_due_date} onChange={(value) => update("show_due_date", value)} />
                    <InvoiceToggle label="Payment mode" checked={form.show_payment_mode} onChange={(value) => update("show_payment_mode", value)} />
                    <InvoiceToggle label="Prepared by" checked={form.show_prepared_by} onChange={(value) => update("show_prepared_by", value)} />
                    <InvoiceToggle label="Invoice status" checked={form.show_status} onChange={(value) => update("show_status", value)} />
                    <InvoiceToggle label="Item discount column" checked={form.show_item_discount} onChange={(value) => update("show_item_discount", value)} />
                    <InvoiceToggle label="Item tax column" checked={form.show_item_tax} onChange={(value) => update("show_item_tax", value)} />
                    <InvoiceToggle label="Discount summary" checked={form.show_discount_summary} onChange={(value) => update("show_discount_summary", value)} />
                    <InvoiceToggle label="Tax/VAT summary" checked={form.show_tax_summary} onChange={(value) => update("show_tax_summary", value)} />
                  </ToggleGroup>

                  <ToggleGroup title="Footer & payment details">
                    <InvoiceToggle label="Amount in words" checked={form.show_amount_in_words} onChange={(value) => update("show_amount_in_words", value)} />
                    <InvoiceToggle label="Bank details" checked={form.show_bank_details} onChange={(value) => update("show_bank_details", value)} />
                    <InvoiceToggle label="Payment record" checked={form.show_payment_record} onChange={(value) => update("show_payment_record", value)} />
                    <InvoiceToggle label="Notes / remarks" checked={form.show_notes} onChange={(value) => update("show_notes", value)} />
                    <InvoiceToggle label="Terms & conditions" checked={form.show_terms} onChange={(value) => update("show_terms", value)} />
                    <InvoiceToggle label="Signature area" checked={form.show_signature} onChange={(value) => update("show_signature", value)} />
                  </ToggleGroup>
                </div>
              </div>

              <div className="flex items-start gap-3 rounded-2xl border border-[#ead4a8] bg-[var(--accent-soft)] p-4 text-sm leading-6 text-[#725020]">
                <ShieldCheck size={19} className="mt-0.5 shrink-0" />
                <p>The toggles control presentation, not tax obligations. For tax invoices, keep the fields required for your registration and customer type enabled. Electronic tax billing/CBMS use still requires the applicable IRD approval and integration.</p>
              </div>
            </CardBody>
          </Card>
        </div>

        <div className="space-y-4">
          <Card>
            <CardHeader title={<span className="flex items-center gap-2"><SlidersHorizontal size={18} className="text-[var(--brand)]" />Workspace rules</span>} />
            <CardBody className="space-y-3">
              <Rule icon={Landmark} title="Business records stay separate" description="Sales, expenses, invoice sequences and reports use this business context." />
              <Rule icon={CalendarClock} title="Historical rates stay fixed" description="New ownership schedules never rewrite a previously closed period." />
              <Rule icon={UsersRound} title="Access follows role" description="Staff see only the business tools permitted by their assigned role." />
            </CardBody>
          </Card>

          {owner ? (
            <Card>
              <CardHeader title={<span className="flex items-center gap-2"><Percent size={18} className="text-[var(--brand)]" />Ownership schedule</span>} description={canManageOwnership ? "Ownership and profit sharing may differ by agreement." : "Only a full-control owner can change ownership stakes."} action={canManageOwnership ? <Button size="sm" variant="quiet" leftIcon={<Plus size={15} />} onClick={(event) => { event.preventDefault(); setOwnershipOpen(true); }}>Change</Button> : undefined} />
              <CardBody className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                  <div className="rounded-2xl bg-[var(--surface-soft)] p-4"><p className="text-[10px] font-bold uppercase tracking-[0.11em] text-[var(--ink-soft)]">Current ownership</p><p className="mt-1.5 text-xl font-black">{number(totalOwnership, 2)}%</p></div>
                  <div className="rounded-2xl bg-[var(--surface-soft)] p-4"><p className="text-[10px] font-bold uppercase tracking-[0.11em] text-[var(--ink-soft)]">Current profit share</p><p className="mt-1.5 text-xl font-black">{number(totalProfitShare, 2)}%</p></div>
                </div>
                <div className="space-y-3">
                  {(ownerships.data ?? []).map((row) => <div key={row.id} className="rounded-2xl border border-[var(--line)] p-4"><div className="flex items-start justify-between gap-3"><div><p className="font-bold">{row.name}</p><p className="mt-0.5 text-xs text-[var(--ink-soft)]">From {prettyDate(row.effective_from)}{row.effective_to ? ` to ${prettyDate(row.effective_to)}` : " onward"}</p></div><Badge tone={row.current ? "success" : "neutral"}>{row.current ? "Current" : "History"}</Badge></div><div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm"><span><strong>{number(row.ownership_percent, 2)}%</strong> ownership</span><span><strong>{number(row.profit_share_percent, 2)}%</strong> profit share</span></div>{row.notes ? <p className="mt-2 text-xs leading-5 text-[var(--ink-soft)]">{row.notes}</p> : null}</div>)}
                </div>
                {!ownerships.isLoading && !ownerships.data?.length ? <p className="text-sm text-[var(--ink-soft)]">No dated ownership records are available.</p> : null}
              </CardBody>
            </Card>
          ) : null}

          {canManageOwnership ? (
            <Card className="border-[var(--danger)]/30">
              <CardHeader title={<span className="flex items-center gap-2 text-[var(--danger)]"><AlertTriangle size={18} />Danger zone</span>} description="This cannot be undone from here — talk to support if you need it back." />
              <CardBody className="space-y-3">
                <p className="text-sm leading-6 text-[var(--ink-soft)]">Deleting <strong>{business.name}</strong> removes it from every member&apos;s workspace, including yours. Its sales, expenses and history are not shown anywhere afterward.</p>
                <FieldShell label={`Type "${business.name}" to confirm`} htmlFor="delete-business-confirm">
                  <Input id="delete-business-confirm" value={deleteConfirmation} onChange={(event) => setDeleteConfirmation(event.target.value)} placeholder={business.name} />
                </FieldShell>
                <Button type="button" variant="danger" leftIcon={<Trash2 size={16} />} disabled={deleteConfirmation !== business.name} loading={deleteBusinessMutation.isPending} onClick={() => deleteBusinessMutation.mutate()}>
                  Delete this business
                </Button>
              </CardBody>
            </Card>
          ) : null}
        </div>
      </form>

      {canManageOwnership ? <OwnershipFormModal businessId={businessId} open={ownershipOpen} onClose={() => setOwnershipOpen(false)} members={team.data ?? []} /> : null}
    </div>
  );
}

function BrandColorField({ label, value, onChange, error }: { label: string; value: string; onChange: (value: string) => void; error?: string }) {
  const pickerValue = /^#[0-9A-Fa-f]{6}$/.test(value) ? value : "#135f48";
  return (
    <div>
      <label className="text-xs font-bold text-[var(--ink)]">{label}</label>
      <div className="mt-1.5 flex h-11 items-center gap-2 rounded-xl border border-[var(--line-strong)] bg-white px-2.5 transition focus-within:border-[var(--brand)] focus-within:ring-3 focus-within:ring-[var(--brand-soft)]">
        <input type="color" value={pickerValue} onChange={(event) => onChange(event.target.value)} className="h-7 w-8 cursor-pointer rounded border-0 bg-transparent p-0" aria-label={`${label} color picker`} />
        <input value={value} onChange={(event) => onChange(event.target.value)} maxLength={7} className="min-w-0 flex-1 bg-transparent text-xs font-bold uppercase outline-none" aria-label={`${label} hex color`} />
      </div>
      {error ? <p className="mt-1 text-xs font-medium text-[var(--danger)]">{error}</p> : null}
    </div>
  );
}

function ToggleGroup({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div>
      <p className="mb-2 text-[10px] font-black uppercase tracking-[0.13em] text-[var(--ink-soft)]">{title}</p>
      <div className="grid gap-2 sm:grid-cols-2">{children}</div>
    </div>
  );
}

function InvoiceToggle({ label, description, checked, onChange }: { label: string; description?: string; checked: boolean; onChange: (value: boolean) => void }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      onClick={() => onChange(!checked)}
      className="flex min-h-12 items-center justify-between gap-3 rounded-xl border border-[var(--line)] bg-white px-3 py-2 text-left transition hover:border-[var(--line-strong)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]/25"
    >
      <span>
        <span className="block text-xs font-bold text-[var(--ink)]">{label}</span>
        {description ? <span className="mt-0.5 block text-[10px] text-[var(--ink-soft)]">{description}</span> : null}
      </span>
      <span className={`relative h-6 w-11 shrink-0 rounded-full transition ${checked ? "bg-[var(--brand)]" : "bg-[#d4dbd6]"}`}>
        <span className={`absolute top-1 h-4 w-4 rounded-full bg-white shadow-sm transition ${checked ? "left-6" : "left-1"}`} />
      </span>
    </button>
  );
}

function Rule({ icon: Icon, title, description }: { icon: typeof Landmark; title: string; description: string }) {
  return <div className="flex gap-3 rounded-2xl border border-[var(--line)] p-3.5"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]"><Icon size={17} /></span><div><p className="text-sm font-bold">{title}</p><p className="mt-0.5 text-xs leading-5 text-[var(--ink-soft)]">{description}</p></div></div>;
}
