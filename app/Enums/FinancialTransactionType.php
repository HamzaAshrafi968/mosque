<?php

namespace App\Enums;

enum FinancialTransactionType: string
{
    case Charge = 'charge';
    case Payment = 'payment';
    case Refund = 'refund';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Charge => 'مستحقات/رسوم',
            self::Payment => 'دفعة',
            self::Refund => 'استرداد',
            self::Transfer => 'تحويل',
            self::Adjustment => 'تسوية',
        };
    }
}
