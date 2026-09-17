<?php

namespace App\Enums;

/**
 * نوع مراجعة 5 (الخمسات):
 *
 * - خمسات ما بعد الحفظ: تُولَّد تلقائياً مع دورة الدفعة عند اكتمال حفظ الجزأين.
 * - خمسات إعادة رسوب الاختبار: تُنشأ للأجزاء الراسبة في الاختبار التراكمي.
 */
enum QuranKhamsaReviewType: string
{
    case PostMemorization = 'post_memorization';
    case RetakeAfterFail = 'retake_after_fail';

    public function label(): string
    {
        return match ($this) {
            self::PostMemorization => 'خمسات ما بعد الحفظ',
            self::RetakeAfterFail => 'خمسات إعادة رسوب الاختبار',
        };
    }

    public function isRetake(): bool
    {
        return $this === self::RetakeAfterFail;
    }
}
