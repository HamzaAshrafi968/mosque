<?php

namespace App\Enums;

enum ShariaAttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'حاضر',
            self::Absent => 'غائب',
            self::Late => 'متأخر',
            self::Excused => 'معذور',
        };
    }

    /** Present and late count as attended for the percentage formula. */
    public function countsAsAttended(): bool
    {
        return in_array($this, [self::Present, self::Late], true);
    }

    /** Excused records are excluded from the percentage denominator. */
    public function qualifiesPercentage(): bool
    {
        return $this !== self::Excused;
    }
}
