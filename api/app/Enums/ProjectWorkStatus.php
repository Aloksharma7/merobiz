<?php

namespace App\Enums;

enum ProjectWorkStatus: string
{
    case Started = 'started';
    case FirstDraft = 'first_draft';
    case SendDraft = 'send_draft';
    case CorrectionOngoing = 'correction_ongoing';
    case WaitingForFeedback = 'waiting_for_feedback';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
