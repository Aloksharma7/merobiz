<?php

namespace App\Enums;

enum ProfitPeriodStatus: string
{
    case Draft = 'draft';
    case Closed = 'closed';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
