<?php

namespace App\Enums;

enum SalaryEntryType: string
{
    case Payment = 'payment';
    case Advance = 'advance';
    case Loan = 'loan';
    case WriteOff = 'write_off';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
