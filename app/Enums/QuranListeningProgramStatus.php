<?php

namespace App\Enums;

/**
 * حالة برنامج الاستماع (إجازة/تأهيلي): 6 دفعات × 5 أجزاء + اختبار تراكمي لكل دفعة.
 */
enum QuranListeningProgramStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'نشط',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغى',
        };
    }
}
