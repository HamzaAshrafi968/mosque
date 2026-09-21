<?php

namespace App\Enums;

/**
 * حالة حفظ طالب الدورة الشرعية (يحدّدها مدير الجامع أو مشرفو الدورة).
 */
enum ShariaMemorizationStatus: string
{
    case NotMemorized = 'not_memorized';
    case PartsMemorized = 'parts_memorized';
    case HalfMemorized = 'half_memorized';
    case Memorized = 'memorized';

    public function label(): string
    {
        return match ($this) {
            self::NotMemorized => 'لم يحفظ',
            self::PartsMemorized => 'حفظ أجزاء منه',
            self::HalfMemorized => 'حفظ النصف',
            self::Memorized => 'حفظ كامل',
        };
    }

    /** لون الشارة في الواجهة. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::NotMemorized => 'bg-red-100 text-red-800',
            self::PartsMemorized => 'bg-amber-100 text-amber-800',
            self::HalfMemorized => 'bg-sky-100 text-sky-800',
            self::Memorized => 'bg-emerald-100 text-emerald-800',
        };
    }

    /** وصف مختصر يظهر في بطاقة الاختيار. */
    public function description(): string
    {
        return match ($this) {
            self::NotMemorized => 'لم يبدأ الحفظ بعد',
            self::PartsMemorized => 'حفظ أجزاء متفرقة من المقرر',
            self::HalfMemorized => 'أتم نصف المقرر',
            self::Memorized => 'أتم حفظ المقرر كاملاً',
        };
    }

    /** لون النص/الحدود عند اختيار الحالة. */
    public function textClass(): string
    {
        return match ($this) {
            self::NotMemorized => 'text-red-700',
            self::PartsMemorized => 'text-amber-700',
            self::HalfMemorized => 'text-sky-700',
            self::Memorized => 'text-emerald-700',
        };
    }
}
