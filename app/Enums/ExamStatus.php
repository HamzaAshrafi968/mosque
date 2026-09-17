<?php

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Published => 'منشور',
            self::Closed => 'مغلق',
        };
    }
}
