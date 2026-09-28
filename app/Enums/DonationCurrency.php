<?php

namespace App\Enums;

/**
 * عملة التبرع المادي: ليرة سورية أو دولار أمريكي (اختيار حر من المتبرع).
 */
enum DonationCurrency: string
{
    case Syp = 'SYP';
    case Usd = 'USD';

    public function label(): string
    {
        return match ($this) {
            self::Syp => 'ل.س',
            self::Usd => 'دولار',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Syp => 'ل.س',
            self::Usd => '$',
        };
    }
}
