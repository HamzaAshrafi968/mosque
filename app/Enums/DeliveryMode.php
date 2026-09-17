<?php

namespace App\Enums;

enum DeliveryMode: string
{
    case Onsite = 'onsite';
    case Online = 'online';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Onsite => 'حضوري',
            self::Online => 'إلكتروني',
            self::Hybrid => 'هجين',
        };
    }

    /** هل يُؤدى الامتحان إلكترونياً (أسئلة داخل النظام)؟ */
    public function isElectronic(): bool
    {
        return in_array($this, [self::Online, self::Hybrid], true);
    }
}
