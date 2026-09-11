"use client";

import { Badge, statusTone } from "@/components/ui/badge";
import { Card, CardBody, CardHeader } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { PageLoading } from "@/components/ui/loading";
import { api } from "@/lib/api";
import { useAuth } from "@/lib/auth-context";
import type { WriterProjectDetail } from "@/lib/types";
import { money, prettyDate } from "@/lib/utils";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, Mail, Phone } from "lucide-react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useEffect } from "react";

export default function WriterProjectDetailPage() {
  const { projectId } = useParams<{ projectId: string }>();
  const { user, isLoading: authLoading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (authLoading) return;
    if (!user) { router.replace("/login"); return; }
    if (user.workspace?.mode !== "writer") router.replace("/");
  }, [authLoading, router, user]);

  const query = useQuery({
    queryKey: ["writer-self-project", projectId],
    queryFn: async () => (await api.get<WriterProjectDetail>(`/writer/projects/${projectId}`)).data,
    enabled: Boolean(user) && user?.workspace?.mode === "writer",
  });

  if (authLoading || !user || user.workspace?.mode !== "writer" || query.isLoading) return <PageLoading />;
  if (query.isError || !query.data) return <div className="mx-auto max-w-3xl p-6"><ErrorState onRetry={() => void query.refetch()} /></div>;

  const project = query.data;
  const currency = project.currency;

  return (
    <div className="min-h-screen bg-[var(--canvas)]">
      <header className="sticky top-0 z-10 border-b border-[var(--line)] bg-white/95 backdrop-blur-xl">
        <div className="mx-auto flex max-w-3xl items-center gap-3 px-4 py-4 sm:px-6">
          <Link href="/writer" className="inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--ink-soft)] hover:text-[var(--brand)]"><ArrowLeft size={15} />Back</Link>
        </div>
      </header>

      <main className="mx-auto max-w-3xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
        <div>
          <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[var(--ink-soft)]">{project.client_name}</p>
          <h1 className="mt-1 text-xl font-black tracking-[-0.02em]">{project.course} · {project.work}</h1>
          <div className="mt-2 flex flex-wrap items-center gap-3">
            <Badge tone={statusTone(project.work_status)}>{project.work_status}</Badge>
            <Badge tone={project.is_current ? "success" : "neutral"}>{project.is_current ? "Currently assigned to you" : "Previously assigned to you"}</Badge>
            {project.deadline ? <span className="text-xs font-semibold text-[var(--ink-soft)]">Deadline {prettyDate(project.deadline)}</span> : null}
          </div>
          {(project.client_phone || project.client_email) ? (
            <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-[var(--ink-soft)]">
              {project.client_phone ? <span className="flex items-center gap-1.5"><Phone size={13} />{project.client_phone}</span> : null}
              {project.client_email ? <span className="flex items-center gap-1.5"><Mail size={13} />{project.client_email}</span> : null}
            </div>
          ) : null}
        </div>

        <Card className="overflow-hidden">
          <CardHeader title="Topic" />
          <CardBody><p className="whitespace-pre-wrap text-base font-bold leading-7 tracking-[-0.01em]">{project.topic}</p></CardBody>
        </Card>

        <section className="grid grid-cols-2 gap-4 xl:grid-cols-3">
          <Stat label="Total amount to you" value={money(project.writer_payment_amount)} />
          <Stat label="Paid to you" value={money(project.writer_paid_amount)} />
          {project.is_current ? <Stat label="Still due to you" value={money(project.writer_due_amount ?? 0)} emphasis={(project.writer_due_amount ?? 0) > 0} /> : null}
        </section>

        {project.assigned_from ? (
          <p className="text-xs text-[var(--ink-soft)]">
            Assigned to you {prettyDate(project.assigned_from)}{project.assigned_to ? ` – ${prettyDate(project.assigned_to)}` : " – now"}
          </p>
        ) : null}
      </main>
    </div>
  );
}

function Stat({ label, value, emphasis }: { label: string; value: string; emphasis?: boolean }) {
  return (
    <Card className={emphasis ? "border-[var(--brand)] bg-[var(--brand-deep)] p-4 text-[var(--on-brand-deep)]" : "p-4"}>
      <p className={`text-[10px] font-bold uppercase tracking-[0.11em] ${emphasis ? "text-[var(--on-brand-deep)]/55" : "text-[var(--ink-soft)]"}`}>{label}</p>
      <p className="mt-1.5 truncate text-lg font-black tracking-[-0.03em]">{value}</p>
    </Card>
  );
}
