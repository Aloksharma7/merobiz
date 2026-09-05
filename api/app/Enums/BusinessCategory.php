<?php

namespace App\Enums;

enum BusinessCategory: string
{
    case Standard = 'standard';
    case Installment = 'installment';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
