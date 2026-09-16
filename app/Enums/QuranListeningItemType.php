<?php

namespace App\Enums;

enum QuranListeningItemType: string
{
    case New = 'new';
    case Review = 'review';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديد',
            self::Review => 'مراجعة 5',
        };
    }
}
