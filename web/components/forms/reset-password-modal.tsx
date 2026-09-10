"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage } from "@/lib/types";
import { useMutation } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";

export function ResetPasswordModal({ businessId, memberId, memberName, open, onClose }: { businessId: string | number; memberId: number; memberName: string; open: boolean; onClose: () => void }) {
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  useEffect(() => { if (open) { setPassword(""); setConfirmation(""); setErrors({}); } }, [open]);

  const mutation = useMutation({
    mutationFn: async () => (await api.post<ApiMessage>(`/businesses/${businessId}/team/${memberId}/reset-password`, { password, password_confirmation: confirmation })).data,
    onSuccess: () => { toast.success(`${memberName}'s password has been reset`); onClose(); },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not reset password", { description: apiError(error) }); },
  });

  return (
    <Modal open={open} onClose={onClose} title="Reset password" description={`Set a new password for ${memberName}. Share it with them directly — it takes effect immediately.`} footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="reset-password-form" loading={mutation.isPending}>Reset password</Button></>}>
      <form id="reset-password-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }} className="space-y-4">
        <FieldShell label="New password" htmlFor="reset-password-value" error={errors.password?.[0]} hint="At least 8 characters, with letters and numbers" required>
          <Input id="reset-password-value" type="password" value={password} onChange={(event) => setPassword(event.target.value)} autoFocus required />
        </FieldShell>
        <FieldShell label="Confirm password" htmlFor="reset-password-confirm" required>
          <Input id="reset-password-confirm" type="password" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} required />
        </FieldShell>
      </form>
    </Modal>
  );
}
