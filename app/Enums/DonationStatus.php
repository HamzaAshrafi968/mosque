<?php

namespace App\Enums;

/**
 * حالة التبرع: بانتظار مراجعة المدير حتى يظهر للعامة، أو مقبول (يظهر)، أو مرفوض.
 */
enum DonationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار المراجعة',
            self::Accepted => 'مقبول',
            self::Rejected => 'مرفوض',
        };
    }
}
