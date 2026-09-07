"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import type { ApiMessage, Customer, Invoice, PaymentMethod, Product } from "@/lib/types";
import { money, today } from "@/lib/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Plus, Trash2 } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import { toast } from "sonner";

type DraftInstallment = { key: string; due_date: string; amount: string; notes: string };

function emptyInstallment(key: string, dueDate: string): DraftInstallment {
  return { key, due_date: dueDate, amount: "", notes: "" };
}

type DraftItem = {
  key: string;
  product_id: string;
  description: string;
  quantity: string;
  unit_price: string;
  unit_cost: string;
  discount_amount: string;
  tax_rate: string;
};

function emptyLine(key: string): DraftItem {
  return { key, product_id: "", description: "", quantity: "1", unit_price: "", unit_cost: "0", discount_amount: "0", tax_rate: "0" };
}

export function SaleFormModal({
  businessId,
  open,
  onClose,
  customers,
  products,
  currency,
  defaultTax = 0,
  canManageCosts = false,
  showBuyerPan = false,
  onCreated,
}: {
  businessId: string | number;
  open: boolean;
  onClose: () => void;
  customers: Customer[];
  products: Product[];
  currency: string;
  defaultTax?: number;
  canManageCosts?: boolean;
  showBuyerPan?: boolean;
  onCreated?: (invoice: Invoice) => void;
}) {
  const { getBusiness, hasFeature } = useBusinesses();
  const installmentsEnabled = hasFeature(getBusiness(businessId), "installments");
  const [customerId, setCustomerId] = useState("");
  const [customerName, setCustomerName] = useState("");
  const [customerPhone, setCustomerPhone] = useState("");
  const [customerEmail, setCustomerEmail] = useState("");
  const [customerAddress, setCustomerAddress] = useState("");
  const [customerPan, setCustomerPan] = useState("");
  const [showCustomerDetails, setShowCustomerDetails] = useState(false);
  const [invoiceDate, setInvoiceDate] = useState(today());
  const [dueDate, setDueDate] = useState(today());
  const [discount, setDiscount] = useState("0");
  const [notes, setNotes] = useState("");
  const [items, setItems] = useState<DraftItem[]>([emptyLine("line-1")]);
  const nextLineKey = useRef(2);
  const [useInstallments, setUseInstallments] = useState(false);
  const [installments, setInstallments] = useState<DraftInstallment[]>([]);
  const nextInstallmentKey = useRef(1);
  const [amountReceived, setAmountReceived] = useState("0");
  const [amountReceivedTouched, setAmountReceivedTouched] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>("bank_transfer");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      setCustomerId("");
      setCustomerName("");
      setCustomerPhone("");
      setCustomerEmail("");
      setCustomerAddress("");
      setCustomerPan("");
      setShowCustomerDetails(false);
      setInvoiceDate(today());
      setDueDate(today());
      setDiscount("0");
      setNotes("");
      nextLineKey.current = 2;
      setItems([{ ...emptyLine("line-1"), tax_rate: String(defaultTax) }]);
      setUseInstallments(false);
      nextInstallmentKey.current = 1;
      setInstallments([]);
      setAmountReceived("0");
      setAmountReceivedTouched(false);
      setPaymentMethod("bank_transfer");
      setErrors({});
    }
  }, [defaultTax, open]);

  const totals = useMemo(() => {
    const lines = items.map((item) => {
      const base = Math.max(0, Number(item.quantity) || 0) * Math.max(0, Number(item.unit_price) || 0);
      const lineDiscount = Math.min(base, Math.max(0, Number(item.discount_amount) || 0));
      return { base, taxable: base - lineDiscount, taxRate: Math.max(0, Number(item.tax_rate) || 0), cost: Math.max(0, Number(item.quantity) || 0) * Math.max(0, Number(item.unit_cost) || 0) };
    });
    const subtotal = lines.reduce((sum, line) => sum + line.base, 0);
    const beforeGlobal = lines.reduce((sum, line) => sum + line.taxable, 0);
    const globalDiscount = Math.min(beforeGlobal, Math.max(0, Number(discount) || 0));
    const tax = lines.reduce((sum, line) => {
      const allocated = beforeGlobal ? globalDiscount * (line.taxable / beforeGlobal) : 0;
      return sum + Math.max(0, line.taxable - allocated) * (line.taxRate / 100);
    }, 0);
    const cost = lines.reduce((sum, line) => sum + line.cost, 0);
    const netSales = beforeGlobal - globalDiscount;
    return { subtotal, discount: lines.reduce((sum, line) => sum + (line.base - line.taxable), 0) + globalDiscount, tax, total: netSales + tax, cost, grossProfit: netSales - cost };
  }, [discount, items]);

  const installmentSum = installments.reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
  const installmentMismatch = useInstallments && Math.abs(installmentSum - totals.total) > 0.01;

  useEffect(() => {
    if (!amountReceivedTouched) setAmountReceived(totals.total > 0 ? totals.total.toFixed(2) : "0");
  }, [amountReceivedTouched, totals.total]);

  const amountReceivedValue = Math.max(0, Number(amountReceived) || 0);
  const balanceAfter = Math.max(0, totals.total - amountReceivedValue);

  function addInstallment() {
    setInstallments((current) => [...current, emptyInstallment(`installment-${nextInstallmentKey.current++}`, dueDate || today())]);
  }
  function updateInstallment(index: number, key: keyof DraftInstallment, value: string) {
    setInstallments((current) => current.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row));
  }
  function removeInstallment(index: number) {
    setInstallments((current) => current.filter((_, rowIndex) => rowIndex !== index));
  }

  const mutation = useMutation({
    mutationFn: async () => {
      const payload = {
        customer_id: customerId ? Number(customerId) : null,
        customer_name: customerName.trim(),
        customer_phone: customerPhone.trim() || null,
        customer_email: customerEmail.trim() || null,
        customer_address: customerAddress.trim() || null,
        customer_pan_number: customerPan.trim() || null,
        invoice_date: invoiceDate,
        due_date: dueDate || null,
        status: "issued",
        discount_amount: Number(discount || 0),
        notes: notes || null,
        items: items.map((item) => ({
          product_id: item.product_id ? Number(item.product_id) : null,
          description: item.description,
          quantity: Number(item.quantity),
          unit_price: Number(item.unit_price),
          ...(canManageCosts ? { unit_cost: Number(item.unit_cost || 0) } : {}),
          discount_amount: Number(item.discount_amount || 0),
          tax_rate: Number(item.tax_rate || 0),
        })),
        ...(useInstallments && installments.length >= 2 ? {
          installments: installments.map((row) => ({ due_date: row.due_date, amount: Number(row.amount), notes: row.notes || null })),
        } : {}),
      };
      const response = (await api.post<ApiMessage<{ invoice: Invoice }>>(`/businesses/${businessId}/invoices`, payload)).data;
      if (amountReceivedValue > 0) {
        const paymentAmount = Math.min(amountReceivedValue, response.invoice.total_amount);
        await api.post(`/businesses/${businessId}/invoices/${response.invoice.id}/payments`, {
          payment_date: invoiceDate,
          amount: paymentAmount,
          method: paymentMethod,
          notes: null,
        });
        const fresh = (await api.get<{ data: Invoice }>(`/businesses/${businessId}/invoices/${response.invoice.id}`)).data.data;
        return { ...response, invoice: fresh };
      }
      return response;
    },
    onSuccess: async (response) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["invoices", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["business-dashboard", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["portfolio-dashboard"] }),
        queryClient.invalidateQueries({ queryKey: ["products", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["customers", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["customer", String(businessId)] }),
      ]);
      const paidInFull = response.invoice.balance_amount <= 0.004;
      toast.success(paidInFull ? "Sale recorded and paid in full" : "Sale recorded", { description: `${response.invoice.invoice_number} · ${money(response.invoice.total_amount, currency)}${response.invoice.paid_amount > 0 && !paidInFull ? ` · ${money(response.invoice.balance_amount, currency)} due` : ""}` });
      onCreated?.(response.invoice);
      onClose();
    },
    onError: (error) => {
      setErrors(fieldErrors(error));
      toast.error("Could not create invoice", { description: apiError(error) });
      void queryClient.invalidateQueries({ queryKey: ["invoices", String(businessId)] });
    },
  });

  function chooseCustomer(value: string) {
    setCustomerId(value);
    if (!value) return;
    const customer = customers.find((row) => row.id === Number(value));
    if (!customer) return;
    setCustomerName(customer.name ?? "");
    setCustomerPhone(customer.phone ?? "");
    setCustomerEmail(customer.email ?? "");
    setCustomerAddress(customer.address ?? "");
    setCustomerPan(customer.pan_number ?? "");
    if (customer.email || customer.address || customer.pan_number) setShowCustomerDetails(true);
  }

  function chooseProduct(index: number, productId: string) {
    const product = products.find((row) => row.id === Number(productId));
    setItems((current) => current.map((item, itemIndex) => itemIndex === index ? {
      ...item,
      product_id: productId,
      description: product?.name ?? item.description,
      unit_price: product ? String(product.sale_price) : item.unit_price,
      unit_cost: product ? String(product.cost_price ?? 0) : item.unit_cost,
      tax_rate: product ? String(product.tax_rate) : item.tax_rate,
    } : item));
  }

  function updateLine(index: number, key: keyof DraftItem, value: string) {
    setItems((current) => current.map((item, itemIndex) => itemIndex === index ? { ...item, [key]: value } : item));
  }

  function removeLine(index: number) {
    setItems((current) => current.length === 1 ? current : current.filter((_, itemIndex) => itemIndex !== index));
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Create sale"
      description="Type the customer name, add the sale, and issue the bill. Saving a separate customer record is optional."
      size="xl"
      footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="sale-form" loading={mutation.isPending}>Issue invoice</Button></>}
    >
      <form id="sale-form" onSubmit={(event) => { event.preventDefault(); setErrors({}); if (installmentMismatch) { toast.error("Installments don't add up", { description: `The installments must total ${money(totals.total, currency)}.` }); return; } mutation.mutate(); }} className="space-y-6">
        <div className="rounded-2xl bg-[var(--surface-soft)] p-4">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <FieldShell label="Customer name" htmlFor="invoice-customer-name" error={errors.customer_name?.[0]} required className="sm:col-span-2">
              <Input id="invoice-customer-name" value={customerName} onChange={(event) => setCustomerName(event.target.value)} placeholder="e.g. Ram Sharma or ABC Traders" required />
            </FieldShell>
            <FieldShell label="Use saved customer" htmlFor="invoice-customer" error={errors.customer_id?.[0]} hint="Optional">
              <Select id="invoice-customer" value={customerId} onChange={(event) => chooseCustomer(event.target.value)}>
                <option value="">Not linked</option>
                {customers.map((customer) => <option value={customer.id} key={customer.id}>{customer.name}</option>)}
              </Select>
            </FieldShell>
            <FieldShell label="Phone" htmlFor="invoice-customer-phone" error={errors.customer_phone?.[0]} hint="Optional">
              <Input id="invoice-customer-phone" value={customerPhone} onChange={(event) => setCustomerPhone(event.target.value)} placeholder="98XXXXXXXX" />
            </FieldShell>
            <FieldShell label="Invoice date" htmlFor="invoice-date" error={errors.invoice_date?.[0]} required>
              <Input id="invoice-date" type="date" max={today()} value={invoiceDate} onChange={(event) => { setInvoiceDate(event.target.value); if (dueDate < event.target.value) setDueDate(event.target.value); }} required />
            </FieldShell>
            <FieldShell label="Payment due" htmlFor="due-date" error={errors.due_date?.[0]}>
              <Input id="due-date" type="date" min={invoiceDate} value={dueDate} onChange={(event) => setDueDate(event.target.value)} />
            </FieldShell>
            <div className="flex items-end sm:col-span-2">
              <Button type="button" variant="quiet" size="sm" onClick={() => setShowCustomerDetails((value) => !value)}>
                {showCustomerDetails ? "Hide extra customer fields" : showBuyerPan ? "Add address, email or PAN" : "Add address or email"}
              </Button>
            </div>
          </div>
          {showCustomerDetails ? (
            <div className="mt-4 grid gap-4 border-t border-[var(--line)] pt-4 sm:grid-cols-2 lg:grid-cols-3">
              <FieldShell label="Address" htmlFor="invoice-customer-address" error={errors.customer_address?.[0]} className="lg:col-span-1">
                <Input id="invoice-customer-address" value={customerAddress} onChange={(event) => setCustomerAddress(event.target.value)} placeholder="Customer address" />
              </FieldShell>
              <FieldShell label="Email" htmlFor="invoice-customer-email" error={errors.customer_email?.[0]}>
                <Input id="invoice-customer-email" type="email" value={customerEmail} onChange={(event) => setCustomerEmail(event.target.value)} placeholder="customer@example.com" />
              </FieldShell>
              {showBuyerPan ? (
                <FieldShell label="Buyer PAN" htmlFor="invoice-customer-pan" error={errors.customer_pan_number?.[0]} hint="Optional">
                  <Input id="invoice-customer-pan" value={customerPan} onChange={(event) => setCustomerPan(event.target.value)} placeholder="PAN if needed" />
                </FieldShell>
              ) : null}
            </div>
          ) : null}
          <p className="mt-3 text-[11px] leading-5 text-[var(--ink-soft)]">For a one-time sale, just enter the customer name. Choosing a saved customer only reuses its details; this invoice does not create a new customer record automatically.</p>
        </div>

        <div>
          <div className="mb-3 flex items-center justify-between gap-3"><div><h3 className="font-extrabold">Invoice items</h3><p className="mt-0.5 text-xs text-[var(--ink-soft)]">{canManageCosts ? "Costs stay internal and are never printed for customers." : "Choose from your assigned company catalogue. Internal cost and margin stay private."}</p></div><Button type="button" variant="quiet" size="sm" leftIcon={<Plus size={15} />} onClick={() => setItems((current) => [...current, { ...emptyLine(`line-${nextLineKey.current++}`), tax_rate: String(defaultTax) }])}>Add line</Button></div>
          <div className="space-y-3">
            {items.map((item, index) => (
              <div key={item.key} className="rounded-2xl border border-[var(--line)] bg-white p-4 shadow-[0_2px_8px_rgb(17_39_29/0.025)]">
                <div className="flex items-center justify-between gap-3"><span className="text-xs font-black uppercase tracking-[0.12em] text-[var(--brand)]">Item {index + 1}</span><button type="button" onClick={() => removeLine(index)} disabled={items.length === 1} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--danger-soft)] hover:text-[var(--danger)] disabled:opacity-30" aria-label={`Remove item ${index + 1}`}><Trash2 size={16} /></button></div>
                <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-12">
                  <FieldShell label="Product or service" htmlFor={`product-${item.key}`} className="lg:col-span-4"><Select id={`product-${item.key}`} value={item.product_id} onChange={(event) => chooseProduct(index, event.target.value)} required={!canManageCosts}><option value="">{canManageCosts ? "Custom line" : "Choose catalog item"}</option>{products.filter((product) => product.active).map((product) => <option value={product.id} key={product.id}>{product.name}</option>)}</Select></FieldShell>
                  <FieldShell label="Description" htmlFor={`description-${item.key}`} error={errors[`items.${index}.description`]?.[0]} required className="lg:col-span-4"><Input id={`description-${item.key}`} value={item.description} onChange={(event) => updateLine(index, "description", event.target.value)} placeholder="e.g. Website design service" required /></FieldShell>
                  <FieldShell label="Quantity" htmlFor={`quantity-${item.key}`} error={errors[`items.${index}.quantity`]?.[0]} required className="lg:col-span-2"><Input id={`quantity-${item.key}`} type="number" min="0.001" step="0.001" placeholder="1" value={item.quantity} onChange={(event) => updateLine(index, "quantity", event.target.value)} required /></FieldShell>
                  <FieldShell label="Unit price" htmlFor={`price-${item.key}`} error={errors[`items.${index}.unit_price`]?.[0]} required className="lg:col-span-2"><Input id={`price-${item.key}`} type="number" min="0" step="0.01" placeholder="0.00" value={item.unit_price} onChange={(event) => updateLine(index, "unit_price", event.target.value)} required /></FieldShell>
                  {canManageCosts ? <FieldShell label="Direct cost" htmlFor={`cost-${item.key}`} error={errors[`items.${index}.unit_cost`]?.[0]} hint="Internal" className="lg:col-span-4"><Input id={`cost-${item.key}`} type="number" min="0" step="0.01" placeholder="0.00" value={item.unit_cost} onChange={(event) => updateLine(index, "unit_cost", event.target.value)} /></FieldShell> : null}
                  <FieldShell label="Line discount" htmlFor={`line-discount-${item.key}`} error={errors[`items.${index}.discount_amount`]?.[0]} className="lg:col-span-4"><Input id={`line-discount-${item.key}`} type="number" min="0" step="0.01" placeholder="0.00" value={item.discount_amount} onChange={(event) => updateLine(index, "discount_amount", event.target.value)} /></FieldShell>
                  <FieldShell label="Tax rate" htmlFor={`tax-${item.key}`} error={errors[`items.${index}.tax_rate`]?.[0]} hint={canManageCosts ? "%" : "Set by catalogue"} className="lg:col-span-4"><Input id={`tax-${item.key}`} type="number" min="0" max="100" step="0.01" placeholder="0" value={item.tax_rate} onChange={(event) => updateLine(index, "tax_rate", event.target.value)} disabled={!canManageCosts} /></FieldShell>
                </div>
              </div>
            ))}
          </div>
        </div>

        {installmentsEnabled ? <div className="rounded-2xl border border-[var(--line)] p-4">
          <label className="flex items-center gap-2.5 text-sm font-bold"><input type="checkbox" checked={useInstallments} onChange={(event) => { setUseInstallments(event.target.checked); if (event.target.checked && installments.length === 0) { setInstallments([emptyInstallment(`installment-${nextInstallmentKey.current++}`, dueDate || today()), emptyInstallment(`installment-${nextInstallmentKey.current++}`, dueDate || today())]); } }} className="h-4 w-4 accent-[var(--brand)]" />Split into installments</label>
          <p className="mt-1.5 text-xs leading-5 text-[var(--ink-soft)]">Agree a down payment and later scheduled payments instead of one full amount.</p>
          {useInstallments ? (
            <div className="mt-4 space-y-3">
              {installments.map((row, index) => (
                <div key={row.key} className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_140px_140px_40px] sm:items-end">
                  <FieldShell label={`Installment ${index + 1}`} htmlFor={`installment-note-${row.key}`} hint="Optional label"><Input id={`installment-note-${row.key}`} value={row.notes} onChange={(event) => updateInstallment(index, "notes", event.target.value)} placeholder="e.g. Down payment" /></FieldShell>
                  <FieldShell label="Due date" htmlFor={`installment-date-${row.key}`}><Input id={`installment-date-${row.key}`} type="date" value={row.due_date} onChange={(event) => updateInstallment(index, "due_date", event.target.value)} /></FieldShell>
                  <FieldShell label="Amount" htmlFor={`installment-amount-${row.key}`}><Input id={`installment-amount-${row.key}`} type="number" min="0.01" step="0.01" placeholder="0.00" value={row.amount} onChange={(event) => updateInstallment(index, "amount", event.target.value)} /></FieldShell>
                  <button type="button" onClick={() => removeInstallment(index)} disabled={installments.length <= 2} className="grid h-11 w-11 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--danger-soft)] hover:text-[var(--danger)] disabled:opacity-30" aria-label={`Remove installment ${index + 1}`}><Trash2 size={16} /></button>
                </div>
              ))}
              <div className="flex items-center justify-between gap-3">
                <Button type="button" variant="quiet" size="sm" leftIcon={<Plus size={15} />} onClick={addInstallment}>Add installment</Button>
                <p className={`text-xs font-semibold ${installmentMismatch ? "text-[var(--danger)]" : "text-[var(--ink-soft)]"}`}>{money(installmentSum, currency)} of {money(totals.total, currency)}</p>
              </div>
            </div>
          ) : null}
        </div> : null}

        <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
          <div className="space-y-4">
            <FieldShell label="Invoice discount" htmlFor="invoice-discount" error={errors.discount_amount?.[0]} hint="Applied across all lines"><Input id="invoice-discount" type="number" min="0" step="0.01" placeholder="0.00" value={discount} onChange={(event) => setDiscount(event.target.value)} /></FieldShell>
            <div className="rounded-2xl border border-[var(--line)] p-4">
              <p className="text-sm font-bold">Payment received</p>
              <p className="mt-1 text-xs leading-5 text-[var(--ink-soft)]">Defaults to the full amount — the payment is recorded together with the invoice, no separate step needed. Lower it for a partial or credit sale.</p>
              <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <FieldShell label="Amount received" htmlFor="amount-received" error={errors.amount?.[0]}>
                  <Input id="amount-received" type="number" min="0" step="0.01" placeholder="0.00" value={amountReceived} onChange={(event) => { setAmountReceivedTouched(true); setAmountReceived(event.target.value); }} />
                </FieldShell>
                <FieldShell label="Payment method" htmlFor="payment-method">
                  <Select id="payment-method" value={paymentMethod} onChange={(event) => setPaymentMethod(event.target.value as PaymentMethod)} disabled={amountReceivedValue <= 0}>
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="qr">QR payment</option>
                    <option value="wallet">Digital wallet</option>
                    <option value="card">Card</option>
                    <option value="cheque">Cheque</option>
                    <option value="other">Other</option>
                  </Select>
                </FieldShell>
              </div>
              {balanceAfter > 0.004 ? <p className="mt-2 text-xs font-semibold text-[var(--danger)]">{money(balanceAfter, currency)} will remain due on this invoice.</p> : null}
            </div>
            <FieldShell label="Notes or payment instructions" htmlFor="invoice-notes" error={errors.notes?.[0]}><Textarea id="invoice-notes" value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Thank you, renewal date, bank details, delivery notes…" /></FieldShell>
          </div>
          <div className="rounded-[20px] bg-[var(--brand-deep)] p-5 text-[var(--on-brand-deep)]">
            <p className="text-xs font-bold uppercase tracking-[0.14em] text-[var(--on-brand-deep)]/45">Invoice summary</p>
            <div className="mt-4 space-y-2.5 text-sm"><SummaryLine label="Subtotal" value={money(totals.subtotal, currency)} /><SummaryLine label="Discount" value={`− ${money(totals.discount, currency)}`} /><SummaryLine label="Tax" value={money(totals.tax, currency)} /><div className="my-3 border-t border-[var(--on-brand-deep)]/12" /><SummaryLine label="Customer total" value={money(totals.total, currency)} strong /><SummaryLine label="Amount received" value={money(amountReceivedValue, currency)} /><SummaryLine label="Balance due" value={money(balanceAfter, currency)} />{canManageCosts ? <><div className="my-3 border-t border-[var(--on-brand-deep)]/12" /><SummaryLine label="Estimated gross profit" value={money(totals.grossProfit, currency)} muted /></> : null}</div>
            <p className="mt-4 text-[11px] leading-5 text-[var(--on-brand-deep)]/42">{canManageCosts ? "Employee commission and business expenses are deducted later to calculate net profit." : "The sale is recorded immediately in this business. No approval step is required."}</p>
          </div>
        </div>
      </form>
    </Modal>
  );
}

function SummaryLine({ label, value, strong, muted }: { label: string; value: string; strong?: boolean; muted?: boolean }) {
  return <div className={`flex items-center justify-between gap-4 ${strong ? "text-base" : ""} ${muted ? "text-[#95d7bf]" : ""}`}><span className={strong ? "font-bold" : "text-[var(--on-brand-deep)]/58"}>{label}</span><span className={strong ? "font-black" : "font-semibold"}>{value}</span></div>;
}
