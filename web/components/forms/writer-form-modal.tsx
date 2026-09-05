"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, PasswordInput, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, Writer } from "@/lib/types";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState, type FormEvent } from "react";
import { toast } from "sonner";

const blank = { name: "", phone: "", email: "", password: "", notes: "", active: true };

export function WriterFormModal({ businessId, open, onClose, writer }: { businessId: string | number; open: boolean; onClose: () => void; writer?: Writer | null }) {
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      setForm(writer ? {
        name: writer.name,
        phone: writer.phone ?? "",
        email: writer.email ?? "",
        password: "",
        notes: writer.notes ?? "",
        active: writer.active,
      } : blank);
      setErrors({});
    }
  }, [writer, open]);

  const mutation = useMutation({
    mutationFn: async () => {
      const path = writer ? `/businesses/${businessId}/writers/${writer.id}` : `/businesses/${businessId}/writers`;
      const response = writer
        ? await api.put<ApiMessage<{ writer: Writer }>>(path, form)
        : await api.post<ApiMessage<{ writer: Writer }>>(path, form);
      return response.data;
    },
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["writers", String(businessId)] }),
        queryClient.invalidateQueries({ queryKey: ["writer", String(businessId)] }),
      ]);
      toast.success(writer ? "Writer updated" : "Writer added");
      onClose();
    },
    onError: (error) => {
      setErrors(fieldErrors(error));
      toast.error("Could not save writer", { description: apiError(error) });
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
    <Modal open={open} onClose={onClose} title={writer ? "Edit writer" : "Add writer"} description="Writers can be assigned to service line items on invoices." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="writer-form" loading={mutation.isPending}>Save writer</Button></>}>
      <form id="writer-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
        <FieldShell label="Writer name" htmlFor="writer-name" error={errors.name?.[0]} required className="sm:col-span-2"><Input id="writer-name" value={form.name} onChange={(event) => update("name", event.target.value)} placeholder="e.g. Priya Sharma" autoFocus required /></FieldShell>
        <FieldShell label="Phone" htmlFor="writer-phone" error={errors.phone?.[0]}><Input id="writer-phone" value={form.phone} onChange={(event) => update("phone", event.target.value)} placeholder="98XXXXXXXX" /></FieldShell>
        <FieldShell label="Email" htmlFor="writer-email" error={errors.email?.[0]}><Input id="writer-email" type="email" value={form.email} onChange={(event) => update("email", event.target.value)} placeholder="name@example.com" /></FieldShell>
        <FieldShell label="Notes" htmlFor="writer-notes" error={errors.notes?.[0]} className="sm:col-span-2"><Textarea id="writer-notes" value={form.notes} onChange={(event) => update("notes", event.target.value)} placeholder="Specialties, rates, or anything worth remembering" /></FieldShell>
        {writer ? <label className="flex items-center gap-2.5 text-sm font-semibold"><input type="checkbox" checked={form.active} onChange={(event) => update("active", event.target.checked)} className="h-4 w-4 accent-[var(--brand)]" />Active writer</label> : null}
        <div className="rounded-2xl border border-[var(--line)] bg-[var(--surface-soft)] p-4 sm:col-span-2">
          <p className="text-sm font-black">{writer?.has_login ? "Writer login" : "Give this writer a login"}</p>
          <p className="mt-0.5 text-xs leading-5 text-[var(--ink-soft)]">
            {writer?.has_login
              ? "This writer can already sign in to see their own current files, past files and payments — read-only, no editing."
              : "Optional — set an email and a temporary password so they can sign in and see only their own current files, past files and payments. Read-only, no editing."}
          </p>
          <div className="mt-3 grid gap-4 sm:grid-cols-2">
            <FieldShell label="Temporary password" htmlFor="writer-password" error={errors.password?.[0]} hint={writer?.has_login ? "Leave blank to keep their current password" : "At least 8 characters"}>
              <PasswordInput id="writer-password" minLength={8} value={form.password} onChange={(event) => update("password", event.target.value)} placeholder={writer?.has_login ? "••••••••" : "Letters and numbers"} disabled={writer?.has_login} />
            </FieldShell>
          </div>
        </div>
      </form>
    </Modal>
  );
}
