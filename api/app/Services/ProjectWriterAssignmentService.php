<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectWriterAssignmentService
{
    public function __construct(private readonly AuditService $audit) {}

    public function assign(
        Project $project,
        int $writerId,
        CarbonImmutable $date,
        ?string $notes,
        User $actor,
        Business $business,
    ): Collection {
        $belongs = $business->writers()->whereKey($writerId)->exists();
        if (! $belongs) {
            throw ValidationException::withMessages([
                'writer_id' => 'The selected writer does not belong to this business.',
            ]);
        }

        DB::transaction(function () use ($project, $writerId, $date, $notes): void {
            $active = $project->writerAssignments()->whereNull('assigned_to')->get();

            foreach ($active as $assignment) {
                if ($assignment->writer_id !== $writerId) {
                    $assignment->update(['assigned_to' => $date->toDateString()]);
                }
            }

            $alreadyActive = $active->contains('writer_id', $writerId);
            if (! $alreadyActive) {
                $project->writerAssignments()->create([
                    'writer_id' => $writerId,
                    'assigned_from' => $date->toDateString(),
                    'assigned_to' => null,
                    'notes' => $notes,
                ]);
            }
        });

        $this->audit->record($actor, $business, 'project.writer_assigned', $project, null, ['writer_id' => $writerId, 'date' => $date->toDateString(), 'notes' => $notes]);

        return $project->writerAssignments()->with('writer')->orderByDesc('assigned_from')->get();
    }
}
