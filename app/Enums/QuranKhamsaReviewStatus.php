<?php

namespace App\Enums;

enum QuranKhamsaReviewStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد المراجعة',
            self::Completed => 'مكتملة',
            self::Cancelled => 'ملغاة',
        };
    }
}
