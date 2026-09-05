"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Select, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage } from "@/lib/types";
import { today } from "@/lib/utils";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState } from "react";
import { toast } from "sonner";

export function ProfitWithdrawalFormModal({ open, onClose, businesses }: { open: boolean; onClose: () => void; businesses: Array<{ id: number; name: string }> }) {
  const [businessId, setBusinessId] = useState("");
  const [withdrawnOn, setWithdrawnOn] = useState(today());
  const [amount, setAmount] = useState("");
  const [notes, setNotes] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();

  useEffect(() => {
    if (open) {
      setBusinessId(businesses[0] ? String(businesses[0].id) : "");
      setWithdrawnOn(today());
      setAmount("");
      setNotes("");
      setErrors({});
    }
  }, [open, businesses]);

  const mutation = useMutation({
    mutationFn: async () => (await api.post<ApiMessage<{ withdrawal: unknown }>>(`/businesses/${businessId}/profit-withdrawals`, {
      withdrawn_on: withdrawnOn, amount: Number(amount), notes: notes || null,
    })).data,
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["business-dashboard", businessId] }),
        queryClient.invalidateQueries({ queryKey: ["portfolio-dashboard"] }),
        queryClient.invalidateQueries({ queryKey: ["personal-overview"] }),
      ]);
      toast.success("Profit withdrawal recorded");
      onClose();
    },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not record withdrawal", { description: apiError(error) }); },
  });

  return (
    <Modal open={open} onClose={onClose} title="Log profit taken" description="Record money you've personally taken out as profit from a business. This only shows on your own profile." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="profit-withdrawal-form" loading={mutation.isPending}>Record withdrawal</Button></>}>
      <form id="profit-withdrawal-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }} className="grid gap-4 sm:grid-cols-2">
        {businesses.length > 1 ? (
          <FieldShell label="Business" htmlFor="withdrawal-business" error={errors.business_id?.[0]} required className="sm:col-span-2">
            <Select id="withdrawal-business" value={businessId} onChange={(event) => setBusinessId(event.target.value)} required>
              {businesses.map((business) => <option value={business.id} key={business.id}>{business.name}</option>)}
            </Select>
          </FieldShell>
        ) : null}
        <FieldShell label="Date" htmlFor="withdrawal-date" error={errors.withdrawn_on?.[0]} required><Input id="withdrawal-date" type="date" max={today()} value={withdrawnOn} onChange={(event) => setWithdrawnOn(event.target.value)} required /></FieldShell>
        <FieldShell label="Amount" htmlFor="withdrawal-amount" error={errors.amount?.[0]} required><Input id="withdrawal-amount" type="number" min="0.01" step="0.01" placeholder="0.00" value={amount} onChange={(event) => setAmount(event.target.value)} required /></FieldShell>
        <FieldShell label="Notes" htmlFor="withdrawal-notes" error={errors.notes?.[0]} className="sm:col-span-2"><Textarea id="withdrawal-notes" value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Any extra detail worth keeping" /></FieldShell>
      </form>
    </Modal>
  );
}
