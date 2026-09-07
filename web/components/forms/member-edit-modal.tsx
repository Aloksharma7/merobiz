"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, BusinessRole, Member, PayType } from "@/lib/types";
import { humanize } from "@/lib/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState, type FormEvent } from "react";
import { toast } from "sonner";

const blank = { role: "employee" as BusinessRole, full_control: false, title: "", commission_rate: "0", active: true, pay_type: "commission" as PayType, salary_amount: "0", salary_visible_to_staff: false, ownership_percent: "0", profit_share_percent: "0" };

export function MemberEditModal({ businessId, actorRole, actorFullControl, actorIsFounder, member, open, onClose }: { businessId: string | number; actorRole: BusinessRole; actorFullControl: boolean; actorIsFounder: boolean; member: Member | null; open: boolean; onClose: () => void }) {
  const roles: BusinessRole[] = actorFullControl ? ["employee", "admin", "owner"] : actorRole === "owner" || actorRole === "admin" ? ["employee", "admin"] : ["employee"];
  const [form, setForm] = useState(blank);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();
  const editingFounder = Boolean(member?.is_founder);
  const lockedByFounderProtection = editingFounder && !actorIsFounder;
  const canEditControl = actorFullControl && !editingFounder;

  useEffect(() => {
    if (open && member) {
      setForm({
        role: member.role,
        full_control: member.full_control,
        title: member.title ?? "",
        commission_rate: String(member.commission_rate ?? 0),
        active: member.active,
        pay_type: member.pay_type ?? "commission",
        salary_amount: String(member.salary_amount ?? 0),
        salary_visible_to_staff: member.salary_visible_to_staff ?? false,
        ownership_percent: String(member.ownership_percent ?? 0),
        profit_share_percent: String(member.profit_share_percent ?? 0),
      });
      setErrors({});
    }
  }, [member, open]);

  const mutation = useMutation({
    mutationFn: async () => {
      if (!member) return;
      const { full_control, ownership_percent, profit_share_percent, ...rest } = form;
      // Only send these if they actually changed from the member's current schedule —
      // otherwise every unrelated edit (e.g. just the job title) would create a fresh,
      // identical dated ownership record.
      const ownershipChanged = actorFullControl && (Number(ownership_percent || 0) !== member.ownership_percent || Number(profit_share_percent || 0) !== member.profit_share_percent);
      return (await api.patch<ApiMessage<{ member: Member }>>(`/businesses/${businessId}/team/${member.id}`, {
        ...rest,
        commission_rate: Number(form.commission_rate || 0),
        salary_amount: Number(form.salary_amount || 0),
        ...(canEditControl ? { full_control } : {}),
        ...(ownershipChanged ? { ownership_percent: Number(ownership_percent || 0), profit_share_percent: Number(profit_share_percent || 0) } : {}),
      })).data;
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["team", String(businessId)] });
      toast.success("Team member updated");
      onClose();
    },
    onError: (error) => {
      setErrors(fieldErrors(error));
      toast.error("Could not update team member", { description: apiError(error) });
    },
  });

  function update<K extends keyof typeof form>(key: K, value: (typeof form)[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    mutation.mutate();
  }

  if (!member) return null;

  return (
    <Modal open={open} onClose={onClose} title={`Edit ${member.name}`} description={editingFounder ? "This person created the business, so they always keep full control." : "Update role, title, commission and access for this team member."} footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="member-edit-form" loading={mutation.isPending}>Save changes</Button></>} size="lg">
      <form id="member-edit-form" onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
        {lockedByFounderProtection ? <p className="rounded-2xl bg-[var(--surface-soft)] p-3 text-xs leading-5 text-[var(--ink-soft)] sm:col-span-2">Only {member.name} can change their own role, active status or full control.</p> : null}
        <FieldShell label="Role" htmlFor="edit-member-role" error={errors.role?.[0]} required><Select id="edit-member-role" value={form.role} disabled={lockedByFounderProtection} onChange={(event) => update("role", event.target.value as BusinessRole)}>{roles.map((role) => <option key={role} value={role}>{humanize(role)}</option>)}</Select></FieldShell>
        <FieldShell label="Job title" htmlFor="edit-member-title" error={errors.title?.[0]}><Input id="edit-member-title" value={form.title} onChange={(event) => update("title", event.target.value)} placeholder="e.g. Sales Executive" /></FieldShell>
        <FieldShell label="Sales commission" htmlFor="edit-commission-rate" error={errors.commission_rate?.[0]} hint="% of net sales"><Input id="edit-commission-rate" type="number" min="0" max="100" step="0.0001" placeholder="0" value={form.commission_rate} onChange={(event) => update("commission_rate", event.target.value)} /></FieldShell>
        <FieldShell label="Pay type" htmlFor="edit-member-pay-type" error={errors.pay_type?.[0]}><Select id="edit-member-pay-type" value={form.pay_type} onChange={(event) => update("pay_type", event.target.value as PayType)}><option value="commission">Commission</option><option value="fixed_salary">Fixed salary</option></Select></FieldShell>
        {form.pay_type === "fixed_salary" ? (
          <>
            <FieldShell label="Monthly salary amount" htmlFor="edit-member-salary-amount" error={errors.salary_amount?.[0]}><Input id="edit-member-salary-amount" type="number" min="0" step="0.01" placeholder="0.00" value={form.salary_amount} onChange={(event) => update("salary_amount", event.target.value)} /></FieldShell>
            <label className="flex items-center gap-2.5 self-end pb-2.5 text-sm font-semibold"><input type="checkbox" checked={form.salary_visible_to_staff} onChange={(event) => update("salary_visible_to_staff", event.target.checked)} className="h-4 w-4 accent-[var(--brand)]" />Visible to this staff member</label>
          </>
        ) : null}
        <label className="flex items-center gap-2.5 self-end pb-2.5 text-sm font-semibold"><input type="checkbox" checked={form.active} disabled={lockedByFounderProtection} onChange={(event) => update("active", event.target.checked)} className="h-4 w-4 accent-[var(--brand)] disabled:opacity-40" />Active member</label>
        {actorFullControl ? (
          <div className="rounded-2xl border border-[var(--line)] p-4 sm:col-span-2">
            <p className="text-sm font-bold">Profit share</p>
            <p className="mt-0.5 text-xs leading-5 text-[var(--ink-soft)]">A cut of the business's overall net profit — separate from sales commission, and available to anyone on the team, not just owners. Leave at 0 if this person shouldn't get a share.</p>
            <div className="mt-3 grid gap-4 sm:grid-cols-2">
              <FieldShell label="Ownership percentage" htmlFor="edit-ownership-percent" error={errors.ownership_percent?.[0]} hint="Equity record only — not used in the profit math"><Input id="edit-ownership-percent" type="number" min="0" max="100" step="0.0001" placeholder="0" value={form.ownership_percent} onChange={(event) => update("ownership_percent", event.target.value)} /></FieldShell>
              <FieldShell label="Profit-share percentage" htmlFor="edit-profit-share-percent" error={errors.profit_share_percent?.[0]} hint="This is the % actually applied to net profit"><Input id="edit-profit-share-percent" type="number" min="0" max="100" step="0.0001" placeholder="0" value={form.profit_share_percent} onChange={(event) => update("profit_share_percent", event.target.value)} /></FieldShell>
            </div>
          </div>
        ) : null}
        {form.role === "owner" ? (
          <label className="flex items-start gap-2.5 rounded-2xl border border-[var(--line)] p-4 text-sm font-semibold sm:col-span-2">
            <input type="checkbox" checked={editingFounder ? true : form.full_control} disabled={!canEditControl} onChange={(event) => update("full_control", event.target.checked)} className="mt-0.5 h-4 w-4 accent-[var(--brand)] disabled:opacity-40" />
            <span>
              <span className="block">Full control (same as the founder)</span>
              <span className="mt-0.5 block text-xs font-normal leading-5 text-[var(--ink-soft)]">{editingFounder ? "The founder always has this." : canEditControl ? "Without this, this co-owner has the same access as an admin — they can't change ownership stakes, grant the owner role, or edit the founder." : "Only a full-control owner can grant this."}</span>
            </span>
          </label>
        ) : null}
      </form>
    </Modal>
  );
}
