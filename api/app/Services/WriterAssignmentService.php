<?php

namespace App\Services;

use App\Models\Business;
use App\Models\InvoiceItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WriterAssignmentService
{
    public function __construct(private readonly AuditService $audit) {}

    /** @param array<int, int> $writerIds */
    public function assign(
        InvoiceItem $item,
        array $writerIds,
        CarbonImmutable $date,
        ?string $notes,
        User $actor,
        Business $business,
    ): Collection {
        $allowsMultiple = $item->product?->allows_multiple_writers ?? false;

        if (! $allowsMultiple && count($writerIds) > 1) {
            throw ValidationException::withMessages([
                'writer_ids' => 'This service only allows one writer at a time.',
            ]);
        }

        $validCount = $business->writers()->whereIn('id', $writerIds)->count();
        if ($validCount !== count(array_unique($writerIds))) {
            throw ValidationException::withMessages([
                'writer_ids' => 'One or more selected writers do not belong to this business.',
            ]);
        }

        DB::transaction(function () use ($item, $writerIds, $date, $notes): void {
            $active = $item->writerAssignments()->whereNull('assigned_to')->get();

            foreach ($active as $assignment) {
                if (! in_array($assignment->writer_id, $writerIds, true)) {
                    $assignment->update(['assigned_to' => $date->toDateString()]);
                }
            }

            $stillActiveWriterIds = $active->pluck('writer_id')->all();

            foreach (array_diff($writerIds, $stillActiveWriterIds) as $writerId) {
                $item->writerAssignments()->create([
                    'writer_id' => $writerId,
                    'assigned_from' => $date->toDateString(),
                    'assigned_to' => null,
                    'notes' => $notes,
                ]);
            }
        });

        $this->audit->record($actor, $business, 'writer.assigned', $item, null, ['writer_ids' => $writerIds, 'date' => $date->toDateString(), 'notes' => $notes]);

        return $item->writerAssignments()->with('writer')->orderByDesc('assigned_from')->get();
    }
}
