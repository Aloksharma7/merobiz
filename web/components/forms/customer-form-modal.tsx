"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, Customer } from "@/lib/types";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState, type FormEvent } from "react";
import { toast } from "sonner";

const blank = { name: "", phone: "", email: "", pan_number: "", address: "", opening_balance: "0", notes: "", active: true };

export function CustomerFormModal({ businessId, open, onClose, customer }: { businessId: string | number; open: boolean; onClose: () => void; customer?: Customer | null }) {
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      setForm(customer ? {
        name: customer.name,
        phone: customer.phone ?? "",
        email: customer.email ?? "",
        pan_number: customer.pan_number ?? "",
        address: customer.address ?? "",
        opening_balance: String(customer.opening_balance ?? 0),
        notes: customer.notes ?? "",
        active: customer.active,
      } : blank);
      setErrors({});
    }
  }, [customer, open]);

  const mutation = useMutation({
    mutationFn: async () => {
      const payload = { ...form, opening_balance: Number(form.opening_balance || 0) };
      const path = customer ? `/businesses/${businessId}/customers/${customer.id}` : `/businesses/${businessId}/customers`;
      const response = customer
        ? await api.put<ApiMessage<{ customer: Customer }>>(path, payload)
        : await api.post<ApiMessage<{ customer: Customer }>>(path, payload);
      return response.data;
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["customers", String(businessId)] });
      toast.success(customer ? "Customer updated" : "Customer added");
      onClose();
    },
    onError: (error) => {
      setErrors(fieldErrors(error));
      toast.error("Could not save customer", { description: apiError(error) });
    },
  });

  function update<K extends keyof typeof form>(key: K, value: (typeof form)[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    mutation.mutate();
  }

  return (
    <Modal open={open} onClose={onClose} title={customer ? "Edit customer" : "Add customer"} description="Only the name is required. Add billing details when they are available." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="customer-form" loading={mutation.isPending}>Save customer</Button></>}>
      <form id="customer-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
        <FieldShell label="Customer name" htmlFor="customer-name" error={errors.name?.[0]} required className="sm:col-span-2"><Input id="customer-name" value={form.name} onChange={(event) => update("name", event.target.value)} autoFocus required /></FieldShell>
        <FieldShell label="Phone" htmlFor="customer-phone" error={errors.phone?.[0]}><Input id="customer-phone" value={form.phone} onChange={(event) => update("phone", event.target.value)} /></FieldShell>
        <FieldShell label="Email" htmlFor="customer-email" error={errors.email?.[0]}><Input id="customer-email" type="email" value={form.email} onChange={(event) => update("email", event.target.value)} /></FieldShell>
        <FieldShell label="PAN number" htmlFor="customer-pan" error={errors.pan_number?.[0]}><Input id="customer-pan" value={form.pan_number} onChange={(event) => update("pan_number", event.target.value)} /></FieldShell>
        <FieldShell label="Opening balance" htmlFor="opening-balance" error={errors.opening_balance?.[0]} hint="Optional"><Input id="opening-balance" type="number" min="0" step="0.01" value={form.opening_balance} onChange={(event) => update("opening_balance", event.target.value)} /></FieldShell>
        <FieldShell label="Address" htmlFor="customer-address" error={errors.address?.[0]} className="sm:col-span-2"><Textarea id="customer-address" value={form.address} onChange={(event) => update("address", event.target.value)} /></FieldShell>
        <FieldShell label="Notes" htmlFor="customer-notes" error={errors.notes?.[0]} className="sm:col-span-2"><Textarea id="customer-notes" value={form.notes} onChange={(event) => update("notes", event.target.value)} /></FieldShell>
        {customer ? <label className="flex items-center gap-2.5 text-sm font-semibold sm:col-span-2"><input type="checkbox" checked={form.active} onChange={(event) => update("active", event.target.checked)} className="h-4 w-4 accent-[var(--brand)]" />Active customer</label> : null}
      </form>
    </Modal>
  );
}
