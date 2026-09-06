<?php

namespace App\Enums;

enum HafizExamStatus: string
{
    case NotTested = 'not_tested';
    case Tested = 'tested';
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NotTested => 'لم يُختبر',
            self::Tested => 'تم الاختبار',
            self::Passed => 'ناجح',
            self::Failed => 'راسب',
        };
    }

    /** not_tested is not the same as failed — it means no exam happened. */
    public function wasTested(): bool
    {
        return in_array($this, [self::Tested, self::Passed, self::Failed], true);
    }
}
