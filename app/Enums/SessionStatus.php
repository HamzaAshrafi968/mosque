<?php

namespace App\Enums;

/**
 * حالة حصة واحدة في تاريخ محدد (استثناء على الحصة الأسبوعية المتكررة).
 */
enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'قائمة',
            self::Postponed => 'مؤجلة',
            self::Cancelled => 'ملغاة',
            self::Completed => 'منتهية',
        };
    }

    /** Whether this exception still needs an action / is upcoming. */
    public function isActiveException(): bool
    {
        return in_array($this, [self::Postponed, self::Cancelled], true);
    }
}
