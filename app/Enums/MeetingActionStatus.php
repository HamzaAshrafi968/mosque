<?php

namespace App\Enums;

enum MeetingActionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'معلق',
            self::Completed => 'منجز',
        };
    }
}
