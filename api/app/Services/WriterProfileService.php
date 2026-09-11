<?php

namespace App\Services;

use App\Http\Resources\WriterResource;
use App\Models\Business;
use App\Models\Project;
use App\Models\Writer;
use App\Models\WriterPayment;
use App\Support\DateRange;

class WriterProfileService
{
    /**
     * Shared by the admin-facing writer profile (WriterController::show, gated on
     * writers.manage) and the writer's own self-service dashboard (gated on being
     * that writer) — both need the identical current/past files and payment picture.
     *
     * @return array<string, mixed>
     */
    public function build(Business $business, Writer $writer, DateRange $range): array
    {
        $projects = $business->projects()
            ->whereHas('writerAssignments', fn ($query) => $query->where('writer_id', $writer->id))
            ->with(['writerAssignments' => fn ($query) => $query->where('writer_id', $writer->id)->orderByDesc('assigned_from')])
            ->latest('id')
            ->get();

        $currentProjects = $projects->filter(fn (Project $project) => $project->writerAssignments
            ->contains(fn ($assignment) => $assignment->assigned_to === null));

        // These are budgeted per project regardless of who's currently assigned, so a
        // due amount needs "paid by anyone" — and the per-project breakdown below also
        // needs "paid by this specific writer". Pulling both as one grouped query per
        // project set avoids firing a separate SUM query for every project in the list.
        $projectIds = $projects->pluck('id');
        $paidByAnyWriterPerProject = WriterPayment::query()
            ->whereIn('project_id', $projectIds)
            ->selectRaw('project_id, SUM(amount) as total')
            ->groupBy('project_id')
            ->pluck('total', 'project_id');
        $paidByThisWriterPerProject = WriterPayment::query()
            ->where('writer_id', $writer->id)
            ->whereIn('project_id', $projectIds)
            ->selectRaw('project_id, SUM(amount) as total')
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        $totalPaid = (float) $writer->payments()->sum('amount');
        $totalDue = (float) $currentProjects->sum(function (Project $project) use ($paidByAnyWriterPerProject) {
            $paid = (float) ($paidByAnyWriterPerProject[$project->id] ?? 0);

            return max(0.0, (float) $project->writer_payment_amount - $paid);
        });

        $periodPayments = $writer->payments()
            ->with('project:id,client_name')
            ->whereBetween('paid_on', [$range->start->toDateString(), $range->end->toDateString()])
            ->orderByDesc('paid_on')
            ->get();

        return [
            'writer' => new WriterResource($writer),
            'business' => ['name' => $business->name, 'currency' => $business->currency],
            'period' => $range->toArray(),
            'stats' => [
                'total_projects' => $projects->count(),
                'current_projects' => $currentProjects->count(),
                'total_agreed' => round((float) $projects->sum('writer_payment_amount'), 2),
                'total_paid' => round($totalPaid, 2),
                'total_due' => round($totalDue, 2),
                'period_paid' => round((float) $periodPayments->sum('amount'), 2),
            ],
            'payments' => $periodPayments->map(fn ($payment): array => [
                'id' => $payment->id,
                'project_id' => $payment->project_id,
                'client_name' => $payment->project?->client_name,
                'paid_on' => $payment->paid_on->toDateString(),
                'amount' => (float) $payment->amount,
                'notes' => $payment->notes,
            ])->values(),
            'projects' => $projects->map(function (Project $project) use ($paidByAnyWriterPerProject, $paidByThisWriterPerProject) {
                $assignment = $project->writerAssignments->first();
                $isCurrent = $assignment && $assignment->assigned_to === null;
                $paidForThisProject = (float) ($paidByThisWriterPerProject[$project->id] ?? 0);
                $paidByAnyone = (float) ($paidByAnyWriterPerProject[$project->id] ?? 0);

                return [
                    'id' => $project->id,
                    'client_name' => $project->client_name,
                    'topic' => $project->topic,
                    'course' => $project->course,
                    'work' => $project->work,
                    'work_status' => $project->work_status->value,
                    'writer_payment_amount' => (float) $project->writer_payment_amount,
                    'writer_paid_amount' => round($paidForThisProject, 2),
                    'writer_due_amount' => $isCurrent ? round(max(0.0, (float) $project->writer_payment_amount - $paidByAnyone), 2) : null,
                    'is_current' => $isCurrent,
                    'assigned_from' => $assignment?->assigned_from->toDateString(),
                    'assigned_to' => $assignment?->assigned_to?->toDateString(),
                ];
            })->values(),
        ];
    }

    /**
     * The writer's own view of a single file — full topic and every non-money
     * detail, plus what the file pays *them* specifically (that's their own
     * earnings, not a business secret). It deliberately omits everything about
     * what the client was charged or has paid: deal amount, collected/due
     * amounts, refunds and profit are the business's figures, not the writer's.
     *
     * @return array<string, mixed>
     */
    public function projectDetail(Writer $writer, Project $project): array
    {
        $assignments = $project->writerAssignments()
            ->where('writer_id', $writer->id)
            ->orderByDesc('assigned_from')
            ->get();
        $currentAssignment = $assignments->first(fn ($assignment) => $assignment->assigned_to === null);
        $latestAssignment = $currentAssignment ?? $assignments->first();

        $paidByThisWriter = (float) $project->writerPayments()->where('writer_id', $writer->id)->sum('amount');
        $paidByAnyone = (float) $project->writerPayments()->sum('amount');

        return [
            'id' => $project->id,
            'currency' => $writer->business->currency,
            'client_name' => $project->client_name,
            'client_phone' => $project->client_phone,
            'client_email' => $project->client_email,
            'started_on' => $project->started_on?->toDateString(),
            'topic' => $project->topic,
            'course' => $project->course,
            'work' => $project->work,
            'work_status' => $project->work_status->value,
            'deadline' => $project->deadline?->toDateString(),
            'is_current' => $currentAssignment !== null,
            'assigned_from' => $latestAssignment?->assigned_from->toDateString(),
            'assigned_to' => $latestAssignment?->assigned_to?->toDateString(),
            'writer_payment_amount' => (float) $project->writer_payment_amount,
            'writer_paid_amount' => round($paidByThisWriter, 2),
            'writer_due_amount' => $currentAssignment ? round(max(0.0, (float) $project->writer_payment_amount - $paidByAnyone), 2) : null,
        ];
    }
}
