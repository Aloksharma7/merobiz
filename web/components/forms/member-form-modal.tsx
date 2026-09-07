"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, PasswordInput, Select } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, BusinessRole, Member, PayType } from "@/lib/types";
import { humanize } from "@/lib/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";

const blank = { name: "", email: "", phone: "", password: "", role: "employee" as BusinessRole, full_control: false, title: "", commission_rate: "0", pay_type: "commission" as PayType, salary_amount: "0", salary_visible_to_staff: false, wants_profit_share: false, ownership_percent: "0", profit_share_percent: "0" };
export function MemberFormModal({ businessId, actorRole, actorFullControl, open, onClose }: { businessId: string | number; actorRole: BusinessRole; actorFullControl: boolean; open: boolean; onClose: () => void }) {
  const roles: BusinessRole[] = actorFullControl ? ["employee", "admin", "owner"] : actorRole === "owner" || actorRole === "admin" ? ["employee", "admin"] : ["employee"];
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();
  useEffect(() => { if (open) { setForm(blank); setErrors({}); } }, [open]);

  const mutation = useMutation({
    mutationFn: async () => {
      const { wants_profit_share, ownership_percent, profit_share_percent, ...rest } = form;
      return (await api.post<ApiMessage<{ member: Member }>>(`/businesses/${businessId}/team`, {
        ...rest,
        commission_rate: Number(form.commission_rate || 0),
        salary_amount: Number(form.salary_amount || 0),
        full_control: form.role === "owner" ? form.full_control : undefined,
        ...(wants_profit_share ? { ownership_percent: Number(ownership_percent || 0), profit_share_percent: Number(profit_share_percent || 0) } : {}),
      })).data;
    },
    onSuccess: async () => { await queryClient.invalidateQueries({ queryKey: ["team", String(businessId)] }); toast.success("Team member added", { description: "Their access follows the selected role." }); onClose(); },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not add team member", { description: apiError(error) }); },
  });

  function update<K extends keyof typeof form>(key: K, value: (typeof form)[K]) { setForm((current) => ({ ...current, [key]: value })); }

  return (
    <Modal open={open} onClose={onClose} title="Add team member" description="Use an existing account email or set a temporary password for a new user." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="member-form" loading={mutation.isPending}>Add member</Button></>} size="lg">
      <form id="member-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }} className="grid gap-4 sm:grid-cols-2">
        <FieldShell label="Full name" htmlFor="member-name" error={errors.name?.[0]} required><Input id="member-name" value={form.name} onChange={(event) => update("name", event.target.value)} placeholder="e.g. Sanjay Yadav" autoFocus required /></FieldShell>
        <FieldShell label="Email" htmlFor="member-email" error={errors.email?.[0]} required><Input id="member-email" type="email" value={form.email} onChange={(event) => update("email", event.target.value)} placeholder="name@example.com" required /></FieldShell>
        <FieldShell label="Phone" htmlFor="member-phone" error={errors.phone?.[0]}><Input id="member-phone" value={form.phone} onChange={(event) => update("phone", event.target.value)} placeholder="98XXXXXXXX" /></FieldShell>
        <FieldShell label="Temporary password" htmlFor="member-password" error={errors.password?.[0]} hint="For new users: at least 8 characters with letters and numbers"><PasswordInput id="member-password" minLength={8} value={form.password} onChange={(event) => update("password", event.target.value)} placeholder="Letters and numbers" /></FieldShell>
        <FieldShell label="Role" htmlFor="member-role" error={errors.role?.[0]} required><Select id="member-role" value={form.role} onChange={(event) => update("role", event.target.value as BusinessRole)}>{roles.map((role) => <option key={role} value={role}>{humanize(role)}</option>)}</Select></FieldShell>
        <FieldShell label="Job title" htmlFor="member-title" error={errors.title?.[0]}><Input id="member-title" value={form.title} onChange={(event) => update("title", event.target.value)} placeholder="e.g. Sales Executive" /></FieldShell>
        <FieldShell label="Sales commission" htmlFor="commission-rate" error={errors.commission_rate?.[0]} hint="% of net sales"><Input id="commission-rate" type="number" min="0" max="100" step="0.0001" placeholder="0" value={form.commission_rate} onChange={(event) => update("commission_rate", event.target.value)} /></FieldShell>
        <FieldShell label="Pay type" htmlFor="member-pay-type" error={errors.pay_type?.[0]}><Select id="member-pay-type" value={form.pay_type} onChange={(event) => update("pay_type", event.target.value as PayType)}><option value="commission">Commission</option><option value="fixed_salary">Fixed salary</option></Select></FieldShell>
        {form.pay_type === "fixed_salary" ? (
          <>
            <FieldShell label="Monthly salary amount" htmlFor="member-salary-amount" error={errors.salary_amount?.[0]}><Input id="member-salary-amount" type="number" min="0" step="0.01" placeholder="0.00" value={form.salary_amount} onChange={(event) => update("salary_amount", event.target.value)} /></FieldShell>
            <label className="flex items-center gap-2.5 self-end pb-2.5 text-sm font-semibold"><input type="checkbox" checked={form.salary_visible_to_staff} onChange={(event) => update("salary_visible_to_staff", event.target.checked)} className="h-4 w-4 accent-[var(--brand)]" />Visible to this staff member</label>
          </>
        ) : null}
        {form.role === "owner" ? (
          <label className="flex items-start gap-2.5 rounded-2xl border border-[var(--line)] p-4 text-sm font-semibold sm:col-span-2">
            <input type="checkbox" checked={form.full_control} onChange={(event) => update("full_control", event.target.checked)} className="mt-0.5 h-4 w-4 accent-[var(--brand)]" />
            <span>
              <span className="block">Full control (same as the founder)</span>
              <span className="mt-0.5 block text-xs font-normal leading-5 text-[var(--ink-soft)]">Without this, this co-owner has the same access as an admin — they can't change ownership stakes, grant the owner role, or edit the founder.</span>
            </span>
          </label>
        ) : null}
        {actorFullControl ? (
          <div className="rounded-2xl border border-[var(--line)] p-4 sm:col-span-2">
            <label className="flex items-start gap-2.5 text-sm font-semibold">
              <input type="checkbox" checked={form.wants_profit_share} onChange={(event) => update("wants_profit_share", event.target.checked)} className="mt-0.5 h-4 w-4 accent-[var(--brand)]" />
              <span>
                <span className="block">Also give this person a share of overall profit</span>
                <span className="mt-0.5 block text-xs font-normal leading-5 text-[var(--ink-soft)]">Separate from sales commission — a cut of the business's total net profit, the same way an owner's share works.</span>
              </span>
            </label>
            {form.wants_profit_share ? (
              <div className="mt-3 grid gap-4 sm:grid-cols-2">
                <FieldShell label="Ownership percentage" htmlFor="member-ownership-percent" error={errors.ownership_percent?.[0]} hint="Equity record only — not used in the profit math"><Input id="member-ownership-percent" type="number" min="0" max="100" step="0.0001" placeholder="0" value={form.ownership_percent} onChange={(event) => update("ownership_percent", event.target.value)} /></FieldShell>
                <FieldShell label="Profit-share percentage" htmlFor="member-profit-share-percent" error={errors.profit_share_percent?.[0]} hint="This is the % actually applied to net profit"><Input id="member-profit-share-percent" type="number" min="0" max="100" step="0.0001" placeholder="0" value={form.profit_share_percent} onChange={(event) => update("profit_share_percent", event.target.value)} /></FieldShell>
              </div>
            ) : null}
          </div>
        ) : null}
        <div className="rounded-2xl border border-[var(--line)] bg-[var(--surface-soft)] p-4 text-xs leading-5 text-[var(--ink-soft)] sm:col-span-2"><span className="font-bold text-[var(--ink)]">Simple rule:</span> employees see only the business they are assigned to and their own sales workflow; admins manage that business.</div>
      </form>
    </Modal>
  );
}
