<?php

namespace App\Enums;

enum PayType: string
{
    case Commission = 'commission';
    case FixedSalary = 'fixed_salary';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
