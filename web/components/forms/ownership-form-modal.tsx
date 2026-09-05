"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, Member } from "@/lib/types";
import { today } from "@/lib/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";

export function OwnershipFormModal({ businessId, open, onClose, members }: { businessId: string | number; open: boolean; onClose: () => void; members: Member[] }) {
  const [userId, setUserId] = useState("");
  const [ownership, setOwnership] = useState("");
  const [profitShare, setProfitShare] = useState("");
  const [effectiveFrom, setEffectiveFrom] = useState(today());
  const [notes, setNotes] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();
  useEffect(() => { if (open) { setUserId(members[0] ? String(members[0].user_id) : ""); setOwnership(""); setProfitShare(""); setEffectiveFrom(today()); setNotes(""); setErrors({}); } }, [members, open]);
  const mutation = useMutation({
    mutationFn: async () => (await api.post<ApiMessage<{ ownership: unknown }>>(`/businesses/${businessId}/ownerships`, { user_id: Number(userId), ownership_percent: Number(ownership), profit_share_percent: Number(profitShare), effective_from: effectiveFrom, notes: notes || null })).data,
    onSuccess: async () => { await Promise.all([queryClient.invalidateQueries({ queryKey: ["ownerships", String(businessId)] }), queryClient.invalidateQueries({ queryKey: ["businesses"] }), queryClient.invalidateQueries({ queryKey: ["business-dashboard", String(businessId)] })]); toast.success("Ownership schedule updated", { description: "Past periods remain unchanged." }); onClose(); },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not save ownership", { description: apiError(error) }); },
  });
  return (
    <Modal open={open} onClose={onClose} title="Schedule ownership change" description="Create a dated record so historical profit is never recalculated using a new percentage." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="ownership-form" loading={mutation.isPending}>Save schedule</Button></>}>
      <form id="ownership-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }} className="space-y-4">
        <FieldShell label="Business partner" htmlFor="owner-user" error={errors.user_id?.[0]} required><Select id="owner-user" value={userId} onChange={(event) => { setUserId(event.target.value); const member = members.find((row) => row.user_id === Number(event.target.value)); if (member) { setOwnership(String(member.ownership_percent)); setProfitShare(String(member.profit_share_percent)); } }}>{members.map((member) => <option value={member.user_id} key={member.user_id}>{member.name} · {member.email}</option>)}</Select></FieldShell>
        <div className="grid gap-4 sm:grid-cols-2"><FieldShell label="Ownership percentage" htmlFor="ownership-percent" error={errors.ownership_percent?.[0]} required><Input id="ownership-percent" type="number" min="0" max="100" step="0.0001" placeholder="e.g. 40" value={ownership} onChange={(event) => setOwnership(event.target.value)} required /></FieldShell><FieldShell label="Profit-share percentage" htmlFor="profit-share-percent" error={errors.profit_share_percent?.[0]} required><Input id="profit-share-percent" type="number" min="0" max="100" step="0.0001" placeholder="e.g. 40" value={profitShare} onChange={(event) => setProfitShare(event.target.value)} required /></FieldShell></div>
        <FieldShell label="Effective from" htmlFor="ownership-date" error={errors.effective_from?.[0]} required><Input id="ownership-date" type="date" value={effectiveFrom} onChange={(event) => setEffectiveFrom(event.target.value)} required /></FieldShell>
        <FieldShell label="Agreement notes" htmlFor="ownership-notes" error={errors.notes?.[0]}><Textarea id="ownership-notes" value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Reference the agreement or reason for the change" /></FieldShell>
      </form>
    </Modal>
  );
}
