<?php

namespace App\Enums;

enum QuranEvaluationResult: string
{
    case Passed = 'passed';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'ناجح',
            self::NeedsReview => 'يحتاج مراجعة',
            self::Failed => 'راسب',
        };
    }

    public function passes(): bool
    {
        return $this === self::Passed;
    }
}
