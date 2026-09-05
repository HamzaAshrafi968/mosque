<?php

namespace App\Enums;

enum FinancialDirection: string
{
    case MoneyIn = 'money_in';
    case MoneyOut = 'money_out';

    public function label(): string
    {
        return match ($this) {
            self::MoneyIn => 'وارد (للشخص)',
            self::MoneyOut => 'صادر (من الشخص)',
        };
    }

    /**
     * The sign the amount contributes to the person's outstanding balance.
     * money_out increases what the person owes; money_in reduces it.
     */
    public function sign(): int
    {
        return $this === self::MoneyOut ? 1 : -1;
    }
}
