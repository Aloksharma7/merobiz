"use client";

import { Button } from "@/components/ui/button";
import { api, apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import type { ApiMessage, User } from "@/lib/types";
import { useMutation } from "@tanstack/react-query";
import { PenLine, Trash2, Upload } from "lucide-react";
import { useRef } from "react";
import { toast } from "sonner";

export function SignatureUploader() {
  const { user, refreshUser } = useAuth();
  const inputRef = useRef<HTMLInputElement>(null);

  const uploadMutation = useMutation({
    mutationFn: async (file: File) => {
      const form = new FormData();
      form.append("signature", file);
      return (await api.post<ApiMessage<{ user: User }>>("/profile/signature", form)).data;
    },
    onSuccess: async () => { await refreshUser(); toast.success("Signature saved"); },
    onError: (error) => toast.error("Could not save signature", { description: apiError(error) }),
  });

  const removeMutation = useMutation({
    mutationFn: async () => (await api.delete<ApiMessage<{ user: User }>>("/profile/signature")).data,
    onSuccess: async () => { await refreshUser(); toast.success("Signature removed"); },
    onError: (error) => toast.error("Could not remove signature", { description: apiError(error) }),
  });

  return (
    <div className="rounded-2xl border border-[var(--line)] p-4">
      <p className="flex items-center gap-1.5 text-sm font-bold"><PenLine size={15} />Signature</p>
      <p className="mt-1 text-xs leading-5 text-[var(--ink-soft)]">Shown on invoices you create instead of a blank signature line.</p>
      {user?.signature_url ? (
        <div className="mt-3 rounded-xl border border-[var(--line)] bg-white p-2">
          <img src={user.signature_url} alt="Your signature" className="h-14 w-full object-contain" />
        </div>
      ) : null}
      <input ref={inputRef} type="file" accept="image/png,image/jpeg,image/webp" className="hidden" onChange={(event) => {
        const file = event.target.files?.[0];
        if (file) uploadMutation.mutate(file);
        event.target.value = "";
      }} />
      <div className="mt-3 flex gap-2">
        <Button type="button" size="sm" variant="secondary" leftIcon={<Upload size={14} />} loading={uploadMutation.isPending} onClick={() => inputRef.current?.click()}>{user?.signature_url ? "Replace" : "Upload"}</Button>
        {user?.signature_url ? <Button type="button" size="sm" variant="ghost" leftIcon={<Trash2 size={14} />} loading={removeMutation.isPending} onClick={() => removeMutation.mutate()}>Remove</Button> : null}
      </div>
    </div>
  );
}
