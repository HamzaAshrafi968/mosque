<?php

namespace App\Enums;

/**
 * حالة دفعة الحفظ (جزآن): تُشتق من الإنجاز الفعلي وتُحفظ على صف الدفعة.
 */
enum QuranMemorizationBatchStatus: string
{
    case Locked = 'locked';
    case PendingMemorization = 'pending_memorization';
    case PendingReview5 = 'pending_review_5';
    case ReadyForTest = 'ready_for_test';
    case Passed = 'passed';
    case NeedsRepeat = 'needs_repeat';

    public function label(): string
    {
        return match ($this) {
            self::Locked => 'مقفلة',
            self::PendingMemorization => 'قيد الحفظ',
            self::PendingReview5 => 'مراجعة 5 مطلوبة',
            self::ReadyForTest => 'بانتظار الاختبار',
            self::Passed => 'ناجحة',
            self::NeedsRepeat => 'تحتاج إعادة',
        };
    }

    /** هل الدفعة ضمن الدورة الحالية للطالب (مفتوحة)؟ */
    public function isOpen(): bool
    {
        return $this !== self::Locked;
    }
}
