<?php

namespace App\Enums;

enum QuranTasmeeResult: string
{
    case Excellent = 'excellent';
    case VeryGood = 'very_good';
    case Good = 'good';
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::Excellent => 'ممتاز',
            self::VeryGood => 'جيد جداً',
            self::Good => 'جيد',
            self::NeedsReview => 'يحتاج مراجعة',
        };
    }

    /** التقدير المقترح من نسبة الإتقان (نفس عتبات واجهة التسميع: 95/85/70). */
    public static function fromMastery(float $mastery): self
    {
        return match (true) {
            $mastery >= 95 => self::Excellent,
            $mastery >= 85 => self::VeryGood,
            $mastery >= 70 => self::Good,
            default => self::NeedsReview,
        };
    }
}
