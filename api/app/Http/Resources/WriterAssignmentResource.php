<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WriterAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'writer' => $this->whenLoaded('writer', fn () => [
                'id' => $this->writer->id,
                'name' => $this->writer->name,
            ]),
            'assigned_from' => $this->assigned_from->toDateString(),
            'assigned_to' => $this->assigned_to?->toDateString(),
            'current' => $this->assigned_to === null,
            'notes' => $this->notes,
        ];
    }
}
