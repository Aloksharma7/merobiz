"use client";

import { ProjectFormModal } from "@/components/forms/project-form-modal";
import { Badge, statusTone } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input, Select } from "@/components/ui/fields";
import { PageLoading, TableLoading } from "@/components/ui/loading";
import { PageHeader } from "@/components/ui/page-header";
import { api } from "@/lib/api";
import { useBusinesses } from "@/lib/business-context";
import { PROJECT_WORK_STATUSES } from "@/lib/project-status";
import type { Paginated, Project, Writer } from "@/lib/types";
import { money, prettyDate, shortTopic, today } from "@/lib/utils";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { CalendarClock, FolderKanban, PenTool, Plus, Search } from "lucide-react";
import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";

export default function ProjectsPage() {
  return (
    <Suspense fallback={<PageLoading />}>
      <ProjectsPageContent />
    </Suspense>
  );
}

function ProjectsPageContent() {
  const { businessId } = useParams<{ businessId: string }>();
  const searchParams = useSearchParams();
  const { getBusiness, can } = useBusinesses();
  const business = getBusiness(businessId);
  const canManage = can(business, "writers.manage");
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [writerId, setWriterId] = useState("all");
  const [page, setPage] = useState(1);
  const [formOpen, setFormOpen] = useState(false);
  useEffect(() => { if (canManage && searchParams.get("new") === "1") setFormOpen(true); }, [canManage, searchParams]);

  const featureEnabled = Boolean(business?.is_installment);
  const query = useQuery({
    queryKey: ["projects", businessId, { search, status, writerId, page }],
    queryFn: async () => (await api.get<Paginated<Project>>(`/businesses/${businessId}/projects`, { params: { search: search || undefined, status: status === "all" ? undefined : status, writer_id: writerId === "all" ? undefined : writerId, page, per_page: 20 } })).data,
    placeholderData: keepPreviousData,
    enabled: canManage && featureEnabled,
  });
  const writersQuery = useQuery({
    queryKey: ["writers", businessId, "roster"],
    queryFn: async () => (await api.get<Paginated<Writer>>(`/businesses/${businessId}/writers`, { params: { active: true, per_page: 100 } })).data.data,
    enabled: canManage && featureEnabled,
  });

  if (!business) return <PageLoading />;

  if (!featureEnabled) {
    return (
      <div className="space-y-7">
        <PageHeader eyebrow={business.name} title="Projects" description="This is an installment / project-based business feature." />
        <Card><EmptyState icon={FolderKanban} title="Projects aren't available for this business" description="Projects are only available for installment-category businesses, set when a business is created." /></Card>
      </div>
    );
  }

  if (query.isError) return <ErrorState onRetry={() => void query.refetch()} />;
  const rows = query.data?.data ?? [];
  const currency = business.currency;

  return (
    <div className="space-y-7">
      <PageHeader eyebrow={business.name} title="Projects" description="Client work, writer assignment and staged payments for each project." actions={canManage ? <Button leftIcon={<Plus size={17} />} onClick={() => setFormOpen(true)}>New project</Button> : undefined} />
      <Card className="overflow-hidden">
        <div className="flex flex-col gap-3 border-b border-[var(--line)] p-4 lg:flex-row lg:items-center">
          <label className="relative min-w-0 flex-1"><span className="sr-only">Search projects</span><Search size={17} className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--ink-soft)]" /><Input className="pl-10" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} placeholder="Search client name or topic" /></label>
          <Select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1); }} className="lg:w-52">
            <option value="all">All statuses</option>
            {PROJECT_WORK_STATUSES.map((row) => <option key={row.value} value={row.value}>{row.label}</option>)}
          </Select>
          <Select value={writerId} onChange={(event) => { setWriterId(event.target.value); setPage(1); }} className="lg:w-48">
            <option value="all">All writers</option>
            {(writersQuery.data ?? []).map((writer) => <option key={writer.id} value={writer.id}>{writer.name}</option>)}
          </Select>
        </div>
        {query.isLoading ? <TableLoading /> : rows.length ? (
          <>
            <div className="grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3">
              {rows.map((project) => (
                <Link href={`/b/${businessId}/projects/${project.id}`} key={project.id} className="block rounded-2xl border border-[var(--line)] p-4 text-left transition hover:-translate-y-0.5 hover:border-[#b7c8bb] hover:shadow-[0_16px_36px_rgb(17_48_35/0.08)]">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0"><p className="truncate font-bold">{project.client_name}</p><p className="mt-0.5 truncate text-sm font-bold text-[var(--ink-soft)]">{shortTopic(project.topic)}</p></div>
                    <Badge tone={statusTone(project.work_status)}>{project.work_status}</Badge>
                  </div>
                  <p className="mt-2.5 text-xs text-[var(--ink-soft)]">{project.course} · {project.work}</p>
                  {project.deadline ? (() => {
                    const overdue = project.deadline! < today() && !["submitted", "approved", "cancelled"].includes(project.work_status);
                    return <p className={`mt-2 flex items-center gap-1.5 text-xs font-semibold ${overdue ? "text-[var(--danger)]" : "text-[var(--ink-soft)]"}`}><CalendarClock size={13} />{overdue ? "Overdue since" : "Deadline"} {prettyDate(project.deadline)}</p>;
                  })() : null}
                  <div className="mt-3 flex items-center justify-between border-t border-[var(--line)] pt-3">
                    <span className="flex items-center gap-1.5 text-xs text-[var(--ink-soft)]"><PenTool size={13} />{project.current_writer?.name ?? "Unassigned"}</span>
                    <span className="text-sm font-black">{money(project.due_amount, currency)} <span className="text-xs font-medium text-[var(--ink-soft)]">due</span></span>
                  </div>
                </Link>
              ))}
            </div>
            <Pager current={query.data?.meta.current_page ?? 1} last={query.data?.meta.last_page ?? 1} onChange={setPage} />
          </>
        ) : <EmptyState icon={FolderKanban} title={search || status !== "all" || writerId !== "all" ? "No matching projects" : "No projects yet"} description={search || status !== "all" || writerId !== "all" ? "Try a different search term or filter." : "Create a project to track a client, their writer and staged payments."} action={canManage ? <Button leftIcon={<Plus size={16} />} onClick={() => setFormOpen(true)}>New project</Button> : undefined} />}
      </Card>
      <ProjectFormModal businessId={businessId} open={canManage && formOpen} onClose={() => setFormOpen(false)} currency={business.currency} />
    </div>
  );
}

function Pager({ current, last, onChange }: { current: number; last: number; onChange: (page: number) => void }) {
  if (last <= 1) return null;
  return <div className="flex items-center justify-between border-t border-[var(--line)] p-3"><Button size="sm" variant="secondary" disabled={current <= 1} onClick={() => onChange(current - 1)}>Previous</Button><span className="text-xs text-[var(--ink-soft)]">Page {current} of {last}</span><Button size="sm" variant="secondary" disabled={current >= last} onClick={() => onChange(current + 1)}>Next</Button></div>;
}
