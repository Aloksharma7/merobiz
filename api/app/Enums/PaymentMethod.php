<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Qr = 'qr';
    case Card = 'card';
    case Wallet = 'wallet';
    case Cheque = 'cheque';
    case Other = 'other';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
