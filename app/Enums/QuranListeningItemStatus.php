<?php

namespace App\Enums;

enum QuranListeningItemStatus: string
{
    case Locked = 'locked';
    case Available = 'available';
    case Listened = 'listened';
    case NeedsRepeat = 'needs_repeat';
    case Passed = 'passed';

    public function label(): string
    {
        return match ($this) {
            self::Locked => 'مقفل',
            self::Available => 'متاح للاستماع',
            self::Listened => 'تم الاستماع',
            self::NeedsRepeat => 'يحتاج إعادة',
            self::Passed => 'ناجح',
        };
    }
}
