"use client";

import { IncomeEntryFormModal } from "@/components/forms/income-entry-form-modal";
import { IncomeSourceFormModal } from "@/components/forms/income-source-form-modal";
import { PersonalNavTabs } from "@/components/personal/personal-nav-tabs";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { PageLoading, TableLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api, apiError } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import type { ApiMessage, Paginated, PersonalIncomeEntry, PersonalIncomeSource } from "@/lib/types";
import { humanize, money, prettyDate } from "@/lib/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Edit3, Landmark, Plus, Trash2, Wallet2 } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";

export default function PersonalIncomePage() {
  const { user } = useAuth();
  const currency = user?.preferred_currency || "NPR";
  const queryClient = useQueryClient();
  const [sourceModal, setSourceModal] = useState<{ open: boolean; source: PersonalIncomeSource | null }>({ open: false, source: null });
  const [entryModal, setEntryModal] = useState(false);

  const sourcesQuery = useQuery({
    queryKey: ["personal-income-sources"],
    queryFn: async () => (await api.get<{ data: PersonalIncomeSource[] }>("/personal/income-sources")).data.data,
  });
  const entriesQuery = useQuery({
    queryKey: ["personal-income-entries"],
    queryFn: async () => (await api.get<Paginated<PersonalIncomeEntry>>("/personal/income-entries", { params: { per_page: 30 } })).data,
  });

  const deleteEntry = useMutation({
    mutationFn: async (id: number) => api.delete(`/personal/income-entries/${id}`),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ["personal-income-entries"] }),
        queryClient.invalidateQueries({ queryKey: ["personal-overview"] }),
      ]);
      toast.success("Income entry deleted");
    },
    onError: (error) => toast.error("Could not delete entry", { description: apiError(error) }),
  });

  const sources = sourcesQuery.data ?? [];
  const activeSources = sources.filter((source) => source.active);
  const entries = entriesQuery.data?.data ?? [];

  return (
    <div className="space-y-7">
      <PageHeader eyebrow="Your finances" title="Income" description="Banks and other sources you earn from outside your businesses, and what you've logged against them." actions={activeSources.length ? <Button leftIcon={<Plus size={17} />} onClick={() => setEntryModal(true)}>Log income</Button> : undefined} />
      <PersonalNavTabs />

      <Card className="overflow-hidden">
        <CardHeader title="Income sources" description="Add a source once, then log entries against it." action={<Button size="sm" variant="secondary" leftIcon={<Plus size={15} />} onClick={() => setSourceModal({ open: true, source: null })}>Add source</Button>} />
        <CardBody className="p-0">
          {sourcesQuery.isLoading ? <TableLoading /> : sources.length ? (
            <div className="divide-y divide-[var(--line)]">
              {sources.map((source) => (
                <div key={source.id} className="flex items-center gap-3 px-5 py-3.5">
                  <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]"><Landmark size={16} /></span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-bold">{source.name}</p>
                    <p className="text-xs text-[var(--ink-soft)]">{humanize(source.type)}{source.notes ? ` · ${source.notes}` : ""}</p>
                  </div>
                  <Badge tone={source.active ? "success" : "neutral"}>{source.active ? "Active" : "Off"}</Badge>
                  <button type="button" onClick={() => setSourceModal({ open: true, source })} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--brand-soft)] hover:text-[var(--brand)]" aria-label={`Edit ${source.name}`}><Edit3 size={15} /></button>
                </div>
              ))}
            </div>
          ) : <EmptyState icon={Landmark} title="No income sources yet" description="Add a bank account or other income source to start logging entries." action={<Button leftIcon={<Plus size={16} />} onClick={() => setSourceModal({ open: true, source: null })}>Add source</Button>} />}
        </CardBody>
      </Card>

      <Card className="overflow-hidden">
        <CardHeader title="Income entries" description="Everything logged against your income sources" />
        <CardBody className="p-0">
          {entriesQuery.isLoading ? <TableLoading /> : entries.length ? (
            <div className="divide-y divide-[var(--line)]">
              {entries.map((entry) => (
                <div key={entry.id} className="flex items-center gap-3 px-5 py-3.5">
                  <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--surface-soft)] text-[var(--brand)]"><Wallet2 size={16} /></span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-bold">{entry.source?.name ?? "—"}</p>
                    <p className="text-xs text-[var(--ink-soft)]">{prettyDate(entry.entry_date)}{entry.notes ? ` · ${entry.notes}` : ""}</p>
                  </div>
                  <p className="text-sm font-black">{money(entry.amount, currency)}</p>
                  <button type="button" onClick={() => { if (window.confirm("Delete this income entry?")) deleteEntry.mutate(entry.id); }} className="grid h-9 w-9 place-items-center rounded-lg text-[var(--ink-soft)] hover:bg-[var(--danger-soft)] hover:text-[var(--danger)]" aria-label="Delete entry"><Trash2 size={15} /></button>
                </div>
              ))}
            </div>
          ) : <EmptyState icon={Wallet2} title="No income logged yet" description="Log a payment received from one of your income sources." action={activeSources.length ? <Button leftIcon={<Plus size={16} />} onClick={() => setEntryModal(true)}>Log income</Button> : undefined} />}
        </CardBody>
      </Card>

      <IncomeSourceFormModal open={sourceModal.open} onClose={() => setSourceModal({ open: false, source: null })} source={sourceModal.source} />
      <IncomeEntryFormModal open={entryModal} onClose={() => setEntryModal(false)} sources={activeSources} />
    </div>
  );
}
