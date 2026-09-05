<?php

namespace App\Enums;

enum ProductBusinessType: string
{
    case Digital = 'digital';
    case Physical = 'physical';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
