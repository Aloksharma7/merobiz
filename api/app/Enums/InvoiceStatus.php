<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
