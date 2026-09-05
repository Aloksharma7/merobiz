"use client";

import { BusinessMark } from "@/components/business-mark";
import { Button, LinkButton } from "@/components/ui/button";
import { ErrorState } from "@/components/ui/error-state";
import { PageLoading } from "@/components/ui/loading";
import { api } from "@/lib/api";
import { brandingFor, businessThemeStyle } from "@/lib/branding";
import { useAuth } from "@/lib/auth-context";
import type { Business, Invoice, Payment } from "@/lib/types";
import { amountInWords, humanize, number, prettyDate } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Printer } from "lucide-react";
import { useParams, useRouter } from "next/navigation";
import { useEffect } from "react";

function invoiceMoney(value: number | string | null | undefined, currency: string) {
  const amount = Number(value ?? 0);
  try {
    return new Intl.NumberFormat("en-NP", {
      style: "currency",
      currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(Number.isFinite(amount) ? amount : 0);
  } catch {
    return `${currency} ${(Number.isFinite(amount) ? amount : 0).toFixed(2)}`;
  }
}

function resourceData<T>(payload: T | { data: T }): T {
  if (payload && typeof payload === "object" && "data" in payload) return payload.data;
  return payload as T;
}

function paymentMode(payments: Payment[], balance: number, paid: number) {
  const methods = Array.from(new Set(payments.map((payment) => payment.method).filter(Boolean)));
  if (!methods.length) return balance > 0 ? "Credit" : paid > 0 ? "Paid" : "—";
  const method = methods.length === 1 ? humanize(methods[0]) : "Mixed payment";
  return balance > 0 ? `${method} / Credit` : method;
}

function taxLabel(invoice: Invoice, vatRegistered: boolean) {
  const rates = Array.from(new Set((invoice.items ?? []).map((item) => Number(item.tax_rate ?? 0)).filter((rate) => rate > 0)));
  const base = vatRegistered ? "VAT" : "Tax";
  return rates.length === 1 ? `${base} ${number(rates[0], rates[0] % 1 ? 2 : 0)}%` : base;
}

export default function PrintableInvoicePage() {
  const { businessId, invoiceId } = useParams<{ businessId: string; invoiceId: string }>();
  const { user, isLoading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!isLoading && !user) router.replace("/login");
  }, [isLoading, router, user]);

  const businessQuery = useQuery({
    queryKey: ["business", businessId, "invoice-print"],
    queryFn: async () => {
      const response = await api.get<Business | { data: Business }>(`/businesses/${businessId}`);
      return resourceData(response.data);
    },
    enabled: Boolean(user),
  });

  const invoiceQuery = useQuery({
    queryKey: ["invoice", businessId, invoiceId, "print"],
    queryFn: async () => {
      const response = await api.get<Invoice | { data: Invoice }>(`/businesses/${businessId}/invoices/${invoiceId}`);
      return resourceData(response.data);
    },
    enabled: Boolean(user),
  });

  const business = businessQuery.data;
  const invoice = invoiceQuery.data;

  const companyName = business?.settings?.invoice?.company_name || business?.name;

  useEffect(() => {
    if (business && invoice) document.title = `${invoice.invoice_number} - ${companyName}`;
  }, [business, invoice, companyName]);

  if (isLoading || !user || businessQuery.isLoading || invoiceQuery.isLoading) return <PageLoading />;
  if (businessQuery.isError || invoiceQuery.isError || !business || !invoice) {
    return <div className="mx-auto max-w-3xl p-6"><ErrorState onRetry={() => void Promise.all([businessQuery.refetch(), invoiceQuery.refetch()])} /></div>;
  }

  const items = invoice.items ?? [];
  const payments = invoice.payments ?? [];
  const settings = business.settings?.invoice ?? {};
  const branding = brandingFor(business);
  const visible = {
    sellerAddress: settings.show_seller_address ?? true,
    sellerPhone: settings.show_seller_phone ?? true,
    sellerEmail: settings.show_seller_email ?? true,
    sellerWebsite: settings.show_seller_website ?? true,
    sellerPan: settings.show_seller_pan ?? true,
    customerAddress: settings.show_customer_address ?? true,
    customerPhone: settings.show_customer_phone ?? true,
    customerEmail: settings.show_customer_email ?? false,
    customerPan: settings.show_customer_pan ?? false,
    dueDate: settings.show_due_date ?? true,
    paymentMode: settings.show_payment_mode ?? true,
    preparedBy: settings.show_prepared_by ?? false,
    status: settings.show_status ?? false,
    itemDiscount: settings.show_item_discount ?? true,
    itemTax: settings.show_item_tax ?? true,
    discountSummary: settings.show_discount_summary ?? true,
    taxSummary: settings.show_tax_summary ?? true,
    amountWords: settings.show_amount_in_words ?? true,
    bankDetails: settings.show_bank_details ?? false,
    paymentRecord: settings.show_payment_record ?? false,
    notes: settings.show_notes ?? true,
    terms: settings.show_terms ?? true,
    signature: settings.show_signature ?? true,
  };

  const customerName = invoice.customer_name || invoice.customer?.name || "Walk-in Customer";
  const customerPhone = invoice.customer_phone || invoice.customer?.phone;
  const customerEmail = invoice.customer_email || invoice.customer?.email;
  const customerAddress = invoice.customer_address || invoice.customer?.address;
  const customerPan = invoice.customer_pan_number || invoice.customer?.pan_number;
  const vatRegistered = Boolean(business.vat_number);
  const sellerPan = business.pan_number || business.vat_number;
  const documentTitle = vatRegistered ? "TAX INVOICE" : "INVOICE";
  const taxableAmount = Math.max(0, Number(invoice.subtotal ?? 0) - Number(invoice.discount_amount ?? 0));
  const issueDate = invoice.finalized_at ?? invoice.created_at;
  const mode = paymentMode(payments, Number(invoice.balance_amount ?? 0), Number(invoice.paid_amount ?? 0));
  const hasBankDetails = visible.bankDetails && Boolean(settings.bank_name || settings.bank_account_name || settings.bank_account_number || settings.bank_branch);
  const remarks = invoice.notes || settings.invoice_note;
  const detailColumns = 5 + (visible.itemDiscount ? 1 : 0) + (visible.itemTax ? 1 : 0);

  return (
    <main className="invoice-screen min-h-screen bg-[#eef0ed] px-3 py-4 sm:px-6 sm:py-8 print:bg-white print:p-0" style={businessThemeStyle(business)}>
      <div className="no-print mx-auto mb-4 flex max-w-[210mm] items-center justify-between gap-3">
        <LinkButton href={`/b/${businessId}/sales`} variant="secondary" leftIcon={<ArrowLeft size={16} />}>Back to sales</LinkButton>
        <Button leftIcon={<Printer size={16} />} onClick={() => window.print()}>Print / Save PDF</Button>
      </div>

      <article className="invoice-paper mx-auto w-full max-w-[210mm] overflow-hidden bg-white text-[#17211c] shadow-[0_24px_70px_rgb(22_34_27/0.13)] print:shadow-none">
        <div className="h-1.5 bg-[var(--brand-deep)]" />
        <div className="px-6 py-7 sm:px-9 sm:py-8 print:px-5 print:py-5">
          <header className="grid gap-6 border-b-2 border-[#18221c] pb-5 sm:grid-cols-[minmax(0,1fr)_245px] print:grid-cols-[minmax(0,1fr)_220px]">
            <div className="flex gap-4">
              {branding.showLogoInvoice ? <BusinessMark business={business} invoice className="h-14 min-w-14 rounded-lg print:h-12 print:min-w-12" imageClassName="p-1" /> : null}
              <div className="min-w-0">
                <h1 className="text-[22px] font-black leading-tight tracking-[-0.025em] text-[#111a15] print:text-[19px]">{companyName}</h1>
                {branding.tagline ? <p className="mt-1 text-[10px] font-semibold tracking-[0.02em] text-[#68736c] print:text-[8.5px]">{branding.tagline}</p> : null}
                <div className="mt-2 space-y-0.5 text-[11px] leading-[1.45] text-[#4d5851] print:text-[9.5px]">
                  {visible.sellerAddress && business.address ? <p>{business.address}</p> : null}
                  {[visible.sellerPhone ? business.phone : null, visible.sellerEmail ? business.email : null, visible.sellerWebsite ? settings.website : null].filter(Boolean).length ? (
                    <p>{[visible.sellerPhone ? business.phone : null, visible.sellerEmail ? business.email : null, visible.sellerWebsite ? settings.website : null].filter(Boolean).join(" · ")}</p>
                  ) : null}
                  {visible.sellerPan && sellerPan ? <p><span className="font-bold text-[#202b25]">Seller PAN:</span> {sellerPan}</p> : null}
                  {visible.sellerPan && business.vat_number && business.vat_number !== sellerPan ? <p><span className="font-bold text-[#202b25]">VAT No.:</span> {business.vat_number}</p> : null}
                </div>
              </div>
            </div>

            <div className="sm:text-right print:text-right">
              <div className="inline-flex border border-[#202b25] px-2 py-1 text-[9px] font-bold uppercase tracking-[0.16em]">Original</div>
              <p className="mt-3 text-[11px] font-bold uppercase tracking-[0.18em] text-[var(--brand)]">{documentTitle}</p>
              <p className="mt-1 text-[20px] font-black tracking-[-0.02em]">{invoice.invoice_number}</p>
              {visible.status ? <p className="mt-2 text-[10px] font-semibold uppercase tracking-[0.08em] text-[#667169]">Status: <span className="text-[#202b25]">{humanize(invoice.status)}</span></p> : null}
            </div>
          </header>

          <section className="grid gap-5 border-b border-[#bcc5be] py-5 sm:grid-cols-[minmax(0,1fr)_minmax(270px,0.9fr)] print:grid-cols-[minmax(0,1fr)_280px] print:py-4">
            <div>
              <p className="invoice-label">Bill To</p>
              <p className="mt-1.5 text-[15px] font-black">{customerName}</p>
              <div className="mt-1.5 space-y-0.5 text-[11px] leading-5 text-[#58635c] print:text-[9.5px] print:leading-4">
                {visible.customerAddress && customerAddress ? <p>{customerAddress}</p> : null}
                {visible.customerPhone && customerPhone ? <p>Phone: {customerPhone}</p> : null}
                {visible.customerEmail && customerEmail ? <p>Email: {customerEmail}</p> : null}
                {visible.customerPan && customerPan ? <p><span className="font-bold text-[#202b25]">Buyer PAN:</span> {customerPan}</p> : null}
              </div>
            </div>

            <dl className="grid grid-cols-[125px_1fr] content-start gap-x-4 gap-y-1.5 text-[11px] print:grid-cols-[115px_1fr] print:text-[9.5px]">
              <MetaRow label="Invoice No." value={invoice.invoice_number} />
              <MetaRow label="Transaction Date" value={prettyDate(invoice.invoice_date)} />
              <MetaRow label="Invoice Issue Date" value={prettyDate(issueDate)} />
              {visible.dueDate && invoice.due_date ? <MetaRow label="Due Date" value={prettyDate(invoice.due_date)} /> : null}
              {visible.paymentMode ? <MetaRow label="Mode of Payment" value={mode} /> : null}
              {visible.preparedBy ? <MetaRow label="Prepared By" value={invoice.creator?.name || "—"} /> : null}
            </dl>
          </section>

          <section className="mt-5 overflow-hidden border border-[#263129] print:mt-4">
            <table className="w-full border-collapse text-left text-[11px] print:text-[9px]">
              <thead>
                <tr className="bg-[#edf1ed] text-[9px] font-bold uppercase tracking-[0.08em] text-[#3e4a43] print:text-[8px]">
                  <th className="w-10 border-r border-[#9fa9a1] px-2 py-2 text-center">S.N.</th>
                  <th className="border-r border-[#9fa9a1] px-3 py-2">Details</th>
                  <th className="w-16 border-r border-[#9fa9a1] px-2 py-2 text-right">Qty</th>
                  <th className="w-24 border-r border-[#9fa9a1] px-2 py-2 text-right">Rate</th>
                  {visible.itemDiscount ? <th className="w-20 border-r border-[#9fa9a1] px-2 py-2 text-right">Discount</th> : null}
                  {visible.itemTax ? <th className="w-16 border-r border-[#9fa9a1] px-2 py-2 text-right">Tax</th> : null}
                  <th className="w-28 px-3 py-2 text-right">Amount</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item, index) => (
                  <tr key={item.id ?? `${index}-${item.description}`} className="invoice-row border-t border-[#c7cec8] align-top">
                    <td className="border-r border-[#c7cec8] px-2 py-3 text-center text-[#5e6962]">{index + 1}</td>
                    <td className="border-r border-[#c7cec8] px-3 py-3">
                      <p className="font-semibold text-[#17211c]">{item.description || item.product_name || "Item"}</p>
                      {item.product_name && item.product_name !== item.description ? <p className="mt-0.5 text-[9px] text-[#727c75]">{item.product_name}</p> : null}
                    </td>
                    <td className="border-r border-[#c7cec8] px-2 py-3 text-right">{number(item.quantity, Number(item.quantity) % 1 ? 2 : 0)}</td>
                    <td className="border-r border-[#c7cec8] px-2 py-3 text-right tabular-nums">{invoiceMoney(item.unit_price, business.currency)}</td>
                    {visible.itemDiscount ? <td className="border-r border-[#c7cec8] px-2 py-3 text-right tabular-nums">{Number(item.discount_amount ?? 0) > 0 ? invoiceMoney(item.discount_amount, business.currency) : "—"}</td> : null}
                    {visible.itemTax ? <td className="border-r border-[#c7cec8] px-2 py-3 text-right">{Number(item.tax_rate ?? 0) > 0 ? `${number(item.tax_rate, Number(item.tax_rate) % 1 ? 2 : 0)}%` : "—"}</td> : null}
                    <td className="px-3 py-3 text-right font-bold tabular-nums">{invoiceMoney(item.line_total, business.currency)}</td>
                  </tr>
                ))}
                {!items.length ? <tr><td colSpan={detailColumns} className="px-4 py-10 text-center text-[#6a756e]">No invoice items.</td></tr> : null}
              </tbody>
            </table>
          </section>

          <section className="mt-5 grid gap-5 sm:grid-cols-[minmax(0,1fr)_300px] print:grid-cols-[minmax(0,1fr)_285px] print:gap-4">
            <div className="space-y-4 text-[10.5px] leading-5 text-[#536058] print:text-[9px] print:leading-4">
              {visible.amountWords ? (
                <div>
                  <p className="invoice-label">Amount in Words</p>
                  <p className="mt-1 font-semibold leading-5 text-[#202b25]">{amountInWords(invoice.total_amount, business.currency)}</p>
                </div>
              ) : null}

              {visible.notes && remarks ? (
                <div>
                  <p className="invoice-label">Remarks</p>
                  <p className="mt-1 whitespace-pre-wrap">{remarks}</p>
                </div>
              ) : null}

              {hasBankDetails ? (
                <div>
                  <p className="invoice-label">Payment Details</p>
                  <div className="mt-1 grid max-w-md grid-cols-[100px_1fr] gap-x-3 gap-y-0.5">
                    {settings.bank_name ? <><span>Bank</span><strong>{settings.bank_name}</strong></> : null}
                    {settings.bank_branch ? <><span>Branch</span><strong>{settings.bank_branch}</strong></> : null}
                    {settings.bank_account_name ? <><span>Account Name</span><strong>{settings.bank_account_name}</strong></> : null}
                    {settings.bank_account_number ? <><span>Account No.</span><strong>{settings.bank_account_number}</strong></> : null}
                  </div>
                </div>
              ) : null}

              {visible.paymentRecord && payments.length ? (
                <div>
                  <p className="invoice-label">Payment Record</p>
                  <div className="mt-1 space-y-0.5">
                    {payments.map((payment) => (
                      <p key={payment.id}>{prettyDate(payment.payment_date)} · {humanize(payment.method)} · <strong>{invoiceMoney(payment.amount, business.currency)}</strong>{payment.reference ? ` · Ref ${payment.reference}` : ""}</p>
                    ))}
                  </div>
                </div>
              ) : null}
            </div>

            <div className="self-start border border-[#263129] text-[11px] print:text-[9.5px]">
              <TotalRow label="Total Amount" value={invoice.subtotal} currency={business.currency} />
              {visible.discountSummary && Number(invoice.discount_amount ?? 0) > 0 ? <TotalRow label="Discount" value={invoice.discount_amount} currency={business.currency} muted /> : null}
              {visible.taxSummary ? <TotalRow label="Taxable Amount" value={taxableAmount} currency={business.currency} /> : null}
              {visible.taxSummary && (Number(invoice.tax_amount ?? 0) > 0 || vatRegistered) ? <TotalRow label={taxLabel(invoice, vatRegistered)} value={invoice.tax_amount} currency={business.currency} /> : null}
              <TotalRow label="Grand Total" value={invoice.total_amount} currency={business.currency} strong />
              {Number(invoice.paid_amount ?? 0) > 0 ? <TotalRow label="Paid Amount" value={invoice.paid_amount} currency={business.currency} muted /> : null}
              {Number(invoice.balance_amount ?? 0) > 0 ? <TotalRow label="Balance Due" value={invoice.balance_amount} currency={business.currency} due /> : null}
            </div>
          </section>

          {(visible.terms || visible.signature) ? (
            <section className={`mt-8 grid gap-8 border-t border-[#bcc5be] pt-5 print:mt-6 print:pt-4 ${visible.signature ? "sm:grid-cols-[minmax(0,1fr)_230px] print:grid-cols-[minmax(0,1fr)_210px]" : "grid-cols-1"}`}>
              {visible.terms ? (
                <div className="text-[10px] leading-[1.45] text-[#657068] print:text-[8.5px] print:leading-[1.4]">
                  <p className="invoice-label">Terms & Conditions</p>
                  <p className="mt-1 whitespace-pre-wrap">{settings.invoice_terms || "Payment is due according to the agreed terms. Please quote the invoice number when making payment."}</p>
                  <p className="mt-3 text-[#7a847e]">This invoice was generated electronically from the seller&apos;s business records.</p>
                </div>
              ) : <div />}
              {visible.signature ? (
                <div className="self-end text-center">
                  <div className="mx-auto h-10 w-40 border-b border-[#202b25]" />
                  <p className="mt-1.5 text-[10px] font-bold">{settings.authorized_name || "Authorized Signature"}</p>
                  {settings.authorized_name ? <p className="text-[9px] text-[#68736c]">{settings.authorized_title || "Authorized Signatory"}</p> : null}
                  <p className="mt-1 text-[8.5px] text-[#7a847e]">For {companyName}</p>
                </div>
              ) : null}
            </section>
          ) : null}

          <footer className="mt-5 flex flex-wrap items-center justify-between gap-x-6 gap-y-1 border-t border-[#d4dad5] pt-3 text-[8.5px] text-[#7a847e] print:mt-4">
            <span>{companyName}{visible.sellerPan && sellerPan ? ` · PAN ${sellerPan}` : ""}</span>
            <span>Invoice Ref: {invoice.invoice_number}</span>
          </footer>
        </div>
      </article>
    </main>
  );
}

function MetaRow({ label, value }: { label: string; value: string }) {
  return <><dt className="font-semibold text-[#667169]">{label}</dt><dd className="text-right font-bold text-[#202b25]">{value}</dd></>;
}

function TotalRow({ label, value, currency, muted = false, strong = false, due = false }: { label: string; value: number | string | null | undefined; currency: string; muted?: boolean; strong?: boolean; due?: boolean }) {
  return (
    <div className={`flex items-center justify-between gap-4 border-b border-[#c7cec8] px-3 py-2 last:border-b-0 ${strong ? "bg-[#edf1ed]" : due ? "bg-[var(--brand-deep)] text-[var(--on-brand-deep)]" : ""}`}>
      <span className={`${strong || due ? "font-black" : "font-semibold"} ${muted && !due ? "text-[#68736c]" : ""}`}>{label}</span>
      <span className={`${strong || due ? "font-black" : "font-semibold"} tabular-nums`}>{invoiceMoney(value, currency)}</span>
    </div>
  );
}
