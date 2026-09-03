<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class DateRange
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        try {
            $start = $request->filled('start')
                ? CarbonImmutable::parse((string) $request->query('start'))->startOfDay()
                : CarbonImmutable::now()->startOfMonth();

            $end = $request->filled('end')
                ? CarbonImmutable::parse((string) $request->query('end'))->endOfDay()
                : CarbonImmutable::now()->endOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => 'The selected date range is invalid.']);
        }

        if ($start->greaterThan($end)) {
            throw ValidationException::withMessages(['date' => 'The start date must be before the end date.']);
        }

        if ($start->diffInDays($end) > 1095) {
            throw ValidationException::withMessages(['date' => 'Please select a range of three years or less.']);
        }

        return new self($start, $end);
    }

    public function previous(): self
    {
        $days = $this->start->diffInDays($this->end) + 1;
        $previousEnd = $this->start->subDay()->endOfDay();

        return new self($previousEnd->subDays($days - 1)->startOfDay(), $previousEnd);
    }

    /** @return array{start: string, end: string, label: string} */
    public function toArray(): array
    {
        return [
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'label' => $this->start->isSameDay($this->end)
                ? $this->start->format('M j, Y')
                : $this->start->format('M j').' – '.$this->end->format('M j, Y'),
        ];
    }
}
