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
}
