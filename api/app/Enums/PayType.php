<?php

namespace App\Enums;

enum PayType: string
{
    case Commission = 'commission';
    case FixedSalary = 'fixed_salary';
    case ProfitShare = 'profit_share';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
