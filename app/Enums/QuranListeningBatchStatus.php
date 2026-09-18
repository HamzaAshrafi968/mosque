<?php

namespace App\Enums;

/**
 * حالة دفعة برنامج الاستماع (5 أجزاء): تُشتق من حالة عناصر الأجزاء.
 */
enum QuranListeningBatchStatus: string
{
    case Locked = 'locked';
    case Listening = 'listening';
    case ReadyForTest = 'ready_for_test';
    case NeedsRepeat = 'needs_repeat';
    case Passed = 'passed';

    public function label(): string
    {
        return match ($this) {
            self::Locked => 'مقفلة',
            self::Listening => 'قيد الاستماع',
            self::ReadyForTest => 'بانتظار الاختبار',
            self::NeedsRepeat => 'تحتاج إعادة',
            self::Passed => 'ناجحة',
        };
    }

    /** هل الدفعة ضمن الدورة الحالية للطالب (مفتوحة)؟ */
    public function isOpen(): bool
    {
        return $this !== self::Locked;
    }
}
