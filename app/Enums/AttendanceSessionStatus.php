<?php

namespace App\Enums;

enum AttendanceSessionStatus: string
{
    case Open = 'open';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'جارية',
            self::Completed => 'مكتملة',
        };
    }
}
