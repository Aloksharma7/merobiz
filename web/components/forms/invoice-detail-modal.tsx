"use client";

import { PaymentFormModal } from "@/components/forms/payment-form-modal";
import { Badge, statusTone } from "@/components/ui/badge";
import { Button, LinkButton } from "@/components/ui/button";
import { Modal } from "@/components/ui/modal";
import { api, apiError } from "@/lib/api";
import type { ApiMessage, Business, Invoice, InvoiceInstallment, Payment } from "@/lib/types";
import { humanize, money, prettyDate } from "@/lib/utils";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { Banknote, Ban, FileCheck2, Pencil, Printer, ReceiptText, Trash2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

export function InvoiceDetailModal({ business, invoice, open, onClose }: { business: Business; invoice: Invoice | null; open: boolean; onClose: () => void }) {
  const [paymentOpen, setPaymentOpen] = useState(false);
  const [editingPayment, setEditingPayment] = useState<Payment | null>(null);
  const queryClient = useQueryClient();
  const canManageAllSales = business.permissions.includes("*") || business.permissions.includes("sales.manage");
  const canCreateOwnSales = business.permissions.includes("sales.create");
  const canManageThisSale = canManageAllSales || canCreateOwnSales;
  const canRecordPayment = business.permissions.includes("*") || business.permissions.includes("payments.manage") || business.permissions.includes("payments.record_own");
  // Deleting a sale (unlike cancelling it) is admin-only, regardless of who created it.
  const canDeleteSale = canManageAllSales;
  const installmentsEnabled = Boolean(business.settings?.features?.installments);

  const installmentsQuery = useQuery({
    queryKey: ["invoice-installments", String(business.id), invoice?.id],
    queryFn: async () => (await api.get<{ data: InvoiceInstallment[] }>(`/businesses/${business.id}/invoices/${invoice?.id}/installments`)).data.data,
    enabled: open && Boolean(invoice) && installmentsEnabled,
  });

  const actionMutation = useMutation({
    mutationFn: async (action: "issue" | "cancel") => (await api.post<ApiMessage<{ invoice: Invoice }>>(`/businesses/${business.id}/invoices/${invoice?.id}/${action}`)).data,
    onSuccess: async (_response, action) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["invoices", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["business-dashboard", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["portfolio-dashboard"] }),
        queryClient.invalidateQueries({ queryKey: ["team", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["projects", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["project", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["customers", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["customer", String(business.id)] }),
      ]);
      toast.success(action === "issue" ? "Invoice issued" : "Invoice cancelled");
      onClose();
    },
    onError: (error) => toast.error("Could not update sale", { description: apiError(error) }),
  });

  const deleteMutation = useMutation({
    mutationFn: async () => api.delete(`/businesses/${business.id}/invoices/${invoice?.id}`),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["invoices", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["business-dashboard", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["portfolio-dashboard"] }),
        queryClient.invalidateQueries({ queryKey: ["team", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["projects", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["project", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["customers", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["customer", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["products", String(business.id)] }),
      ]);
      toast.success("Sale deleted");
      onClose();
    },
    onError: (error) => toast.error("Could not delete sale", { description: apiError(error) }),
  });

  const deletePaymentMutation = useMutation({
    mutationFn: async (payment: Payment) => api.delete(`/businesses/${business.id}/invoices/${invoice?.id}/payments/${payment.id}`),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["invoices", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["invoice", String(business.id), invoice?.id] }),
        queryClient.invalidateQueries({ queryKey: ["business-dashboard", String(business.id)] }),
        queryClient.invalidateQueries({ queryKey: ["portfolio-dashboard"] }),
      ]);
      toast.success("Payment undone");
    },
    onError: (error) => toast.error("Could not undo this payment", { description: apiError(error) }),
  });

  if (!invoice) return null;

  return (
    <>
      <Modal open={open} onClose={onClose} title={invoice.invoice_number} description={`${prettyDate(invoice.invoice_date)} · ${invoice.customer_name || invoice.customer?.name || "Walk-in customer"}`} size="lg" footer={
        <>
          <Button variant="ghost" onClick={onClose}>Close</Button>
          {invoice.status === "draft" && canManageThisSale ? <Button variant="secondary" leftIcon={<FileCheck2 size={16} />} onClick={() => actionMutation.mutate("issue")} loading={actionMutation.isPending}>Issue</Button> : null}
          {!(invoice.status === "draft" || ["cancelled", "refunded"].includes(invoice.status)) && invoice.paid_amount === 0 && canManageThisSale ? <Button variant="secondary" leftIcon={<Ban size={16} />} onClick={() => actionMutation.mutate("cancel")} loading={actionMutation.isPending}>Cancel</Button> : null}
          {invoice.balance_amount > 0 && !(["draft", "cancelled", "refunded"].includes(invoice.status)) && canRecordPayment ? <Button leftIcon={<Banknote size={16} />} onClick={() => setPaymentOpen(true)}>Record payment</Button> : null}
          {canDeleteSale ? <Button variant="secondary" leftIcon={<Trash2 size={16} />} loading={deleteMutation.isPending} onClick={() => { if (window.confirm("Delete this sale? This permanently removes the invoice and its payments, and cannot be undone.")) deleteMutation.mutate(); }}>Delete</Button> : null}
        </>
      }>
        <div className="space-y-6">
          <div className="rounded-2xl bg-[var(--surface-soft)] p-4">
            <p className="text-xs font-bold uppercase tracking-[0.12em] text-[var(--ink-soft)]">Invoice status</p>
            <div className="mt-2"><Badge tone={statusTone(invoice.status)}>{invoice.status}</Badge></div>
            <p className="mt-2 text-xs leading-5 text-[var(--ink-soft)]">Sales are recorded immediately when entered. No approval step is required.</p>
          </div>

          <div className="flex justify-end"><LinkButton href={`/print/invoices/${business.id}/${invoice.id}`} target="_blank" variant="secondary" size="sm" leftIcon={<Printer size={15} />}>Print or PDF</LinkButton></div>

          <div className="overflow-hidden rounded-2xl border border-[var(--line)]"><div className="overflow-x-auto"><table className="w-full min-w-[620px] text-left text-sm"><thead><tr className="bg-[var(--surface-soft)] text-[10px] uppercase tracking-[0.11em] text-[var(--ink-soft)]"><th className="px-4 py-3 font-bold">Description</th><th className="px-3 py-3 text-right font-bold">Qty</th><th className="px-3 py-3 text-right font-bold">Rate</th><th className="px-3 py-3 text-right font-bold">Tax</th><th className="px-4 py-3 text-right font-bold">Total</th></tr></thead><tbody className="divide-y divide-[var(--line)]">{invoice.items?.map((item) => <tr key={item.id}><td className="px-4 py-3.5 font-semibold">{item.description}</td><td className="px-3 py-3.5 text-right">{item.quantity}</td><td className="px-3 py-3.5 text-right">{money(item.unit_price, business.currency)}</td><td className="px-3 py-3.5 text-right">{item.tax_rate}%</td><td className="px-4 py-3.5 text-right font-bold">{money(item.line_total, business.currency)}</td></tr>)}</tbody></table></div></div>

          {installmentsQuery.data?.length ? (
            <div>
              <h3 className="mb-3 font-extrabold">Payment plan</h3>
              <div className="space-y-2">
                {installmentsQuery.data.map((installment) => (
                  <div key={installment.id} className="flex items-center justify-between gap-3 rounded-xl border border-[var(--line)] p-3">
                    <div>
                      <p className="text-sm font-bold">{installment.notes || `Installment ${installment.sequence}`}</p>
                      <p className="text-xs text-[var(--ink-soft)]">Due {prettyDate(installment.due_date)}</p>
                    </div>
                    <div className="flex items-center gap-3">
                      <Badge tone={installment.status === "paid" ? "success" : installment.status === "overdue" ? "danger" : installment.status === "partial" ? "info" : "neutral"}>{humanize(installment.status)}</Badge>
                      <p className="font-black">{money(installment.amount, business.currency)}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ) : null}

          <div className="ml-auto max-w-sm space-y-2 rounded-2xl bg-[var(--brand-deep)] p-5 text-sm text-[var(--on-brand-deep)]"><Line label="Subtotal" value={money(invoice.subtotal, business.currency)} /><Line label="Discount" value={`− ${money(invoice.discount_amount, business.currency)}`} /><Line label="Tax" value={money(invoice.tax_amount, business.currency)} /><div className="border-t border-[var(--on-brand-deep)]/12 pt-2"><Line label="Invoice total" value={money(invoice.total_amount, business.currency)} strong /></div><Line label="Paid" value={money(invoice.paid_amount, business.currency)} /><div className="border-t border-[var(--on-brand-deep)]/12 pt-2"><Line label="Balance" value={money(invoice.balance_amount, business.currency)} strong /></div></div>

          {invoice.payments?.length ? <div><h3 className="mb-3 font-extrabold">Payment history</h3><div className="space-y-2">{invoice.payments.map((payment) => <div key={payment.id} className="flex items-center gap-3 rounded-xl border border-[var(--line)] p-3"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]"><ReceiptText size={16} /></span><div className="min-w-0 flex-1"><p className="text-sm font-bold">{payment.payment_number}</p><p className="text-xs text-[var(--ink-soft)]">{prettyDate(payment.payment_date)} · {humanize(payment.method)}</p></div><p className="font-black">{money(payment.amount, business.currency)}</p>{canRecordPayment ? <div className="flex shrink-0 items-center gap-1"><button type="button" onClick={() => setEditingPayment(payment)} className="grid h-8 w-8 place-items-center rounded-lg text-[var(--ink-soft)] transition hover:bg-[var(--surface-soft)]" aria-label="Edit payment"><Pencil size={14} /></button><button type="button" disabled={deletePaymentMutation.isPending} onClick={() => { if (window.confirm("Undo this payment? This removes it entirely, as if it never happened.")) deletePaymentMutation.mutate(payment); }} className="grid h-8 w-8 place-items-center rounded-lg text-[var(--ink-soft)] transition hover:bg-[var(--danger-soft)] hover:text-[var(--danger)] disabled:opacity-40" aria-label="Undo this payment"><Trash2 size={14} /></button></div> : null}</div>)}</div></div> : null}
          {invoice.notes ? <div className="rounded-2xl border border-[var(--line)] bg-[var(--surface-soft)] p-4"><p className="text-xs font-bold uppercase tracking-[0.11em] text-[var(--ink-soft)]">Notes</p><p className="mt-2 whitespace-pre-wrap text-sm leading-6">{invoice.notes}</p></div> : null}
        </div>
      </Modal>
      <PaymentFormModal businessId={business.id} invoice={invoice} open={paymentOpen} onClose={() => { setPaymentOpen(false); onClose(); }} currency={business.currency} />
      <PaymentFormModal businessId={business.id} invoice={invoice} payment={editingPayment} open={!!editingPayment} onClose={() => setEditingPayment(null)} currency={business.currency} />
    </>
  );
}

function Line({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return <div className="flex items-center justify-between gap-4"><span className={strong ? "font-bold" : "text-[var(--on-brand-deep)]/60"}>{label}</span><span className={strong ? "text-base font-black" : "font-semibold"}>{value}</span></div>;
}
