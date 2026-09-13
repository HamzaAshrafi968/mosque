<?php

namespace App\Enums;

/**
 * مصدر خيارات الخصيصة: كتابة يدوية، أو توليدها من جدول الطلاب
 * مع مرشّحات حسب صفاتهم (الحالة، الجنس، العمر، عدد الأجزاء، الصف).
 */
enum AttributeOptionSource: string
{
    case Manual = 'manual';
    case Students = 'students';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'كتابة يدوية',
            self::Students => 'اختيار من الطلاب',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
