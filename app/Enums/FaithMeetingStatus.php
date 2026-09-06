<?php

namespace App\Enums;

enum FaithMeetingStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'مجدول',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغي',
        };
    }
}
