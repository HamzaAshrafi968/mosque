<?php

namespace App\Enums;

enum QuranCompletionStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار التأكيد',
            self::Confirmed => 'مؤكد',
        };
    }
}
