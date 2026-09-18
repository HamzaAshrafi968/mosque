<?php

namespace App\Enums;

enum PayType: string
{
    case Monthly = 'monthly';
    case Hourly = 'hourly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'راتب شهري',
            self::Hourly => 'بالساعة',
        };
    }
}
