<?php

namespace App\Enums;

enum QuranKhamsaItemStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد المراجعة',
            self::Completed => 'تمت',
        };
    }
}
