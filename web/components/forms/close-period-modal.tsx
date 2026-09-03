"use client";

import { Button } from "@/components/ui/button";
import { FieldShell, Input, Textarea } from "@/components/ui/fields";
import { Modal } from "@/components/ui/modal";
import { api, apiError, fieldErrors } from "@/lib/api";
import type { ApiMessage, ProfitPeriod } from "@/lib/types";
import { format, startOfMonth, subDays, subMonths } from "date-fns";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { LockKeyhole } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";

function previousMonth() {
  const month = subMonths(new Date(), 1);
  return { start: format(startOfMonth(month), "yyyy-MM-dd"), end: format(subDays(startOfMonth(new Date()), 1), "yyyy-MM-dd") };
}

export function ClosePeriodModal({ businessId, open, onClose }: { businessId: string | number; open: boolean; onClose: () => void }) {
  const defaultDates = previousMonth();
  const [start, setStart] = useState(defaultDates.start);
  const [end, setEnd] = useState(defaultDates.end);
  const [notes, setNotes] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const queryClient = useQueryClient();
  useEffect(() => { if (open) { const dates = previousMonth(); setStart(dates.start); setEnd(dates.end); setNotes(""); setErrors({}); } }, [open]);
  const mutation = useMutation({
    mutationFn: async () => (await api.post<ApiMessage<{ period: ProfitPeriod }>>(`/businesses/${businessId}/profit-periods`, { start_date: start, end_date: end, notes: notes || null })).data,
    onSuccess: async (response) => { await Promise.all([queryClient.invalidateQueries({ queryKey: ["profit-periods", String(businessId)] }), queryClient.invalidateQueries({ queryKey: ["portfolio-dashboard"] })]); toast.success("Profit period closed", { description: `${response.period.allocations.length} partner allocation(s) created.` }); onClose(); },
    onError: (error) => { setErrors(fieldErrors(error)); toast.error("Could not close period", { description: apiError(error) }); },
  });
  return (
    <Modal open={open} onClose={onClose} title="Close profit period" description="Snapshot the result and allocate net profit using ownership agreements effective during these dates." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button type="submit" form="close-period-form" leftIcon={<LockKeyhole size={16} />} loading={mutation.isPending}>Close and allocate</Button></>}>
      <form id="close-period-form" onSubmit={(event) => { event.preventDefault(); mutation.mutate(); }} className="space-y-5">
        <div className="rounded-2xl border border-[#ead4a8] bg-[var(--accent-soft)] p-4 text-sm leading-6 text-[#76501b]"><strong>Before closing:</strong> confirm invoices, payments, direct costs, employee commissions and approved expenses. Closed dates cannot receive a conflicting ownership change.</div>
        <div className="grid gap-4 sm:grid-cols-2"><FieldShell label="Period start" htmlFor="period-start" error={errors.start_date?.[0]} required><Input id="period-start" type="date" value={start} max={end} onChange={(event) => setStart(event.target.value)} required /></FieldShell><FieldShell label="Period end" htmlFor="period-end" error={errors.end_date?.[0]} required><Input id="period-end" type="date" value={end} min={start} max={format(new Date(), "yyyy-MM-dd")} onChange={(event) => setEnd(event.target.value)} required /></FieldShell></div>
        <FieldShell label="Closing notes" htmlFor="closing-notes" error={errors.notes?.[0]}><Textarea id="closing-notes" value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Optional approval or adjustment note" /></FieldShell>
      </form>
    </Modal>
  );
}
