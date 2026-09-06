<?php

namespace App\Enums;

enum FaithMeetingAttendanceStatus: string
{
    case Attended = 'attended';
    case Absent = 'absent';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Attended => 'حاضر',
            self::Absent => 'غائب',
            self::Excused => 'معذور',
        };
    }
}
