<?php

namespace App\Enums;

enum ProductType: string
{
    case Product = 'product';
    case Service = 'service';
    case DigitalSubscription = 'digital_subscription';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
