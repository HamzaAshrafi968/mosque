<?php

namespace App\Enums;

/**
 * أنواع أسئلة الامتحان الإلكتروني.
 */
enum QuestionType: string
{
    case Mcq = 'mcq';
    case Checkbox = 'checkbox';
    case TrueFalse = 'true_false';
    case Short = 'short';
    case Essay = 'essay';

    public function label(): string
    {
        return match ($this) {
            self::Mcq => 'اختيار من متعدد (إجابة واحدة)',
            self::Checkbox => 'اختيار متعدد (أكثر من إجابة)',
            self::TrueFalse => 'صح / خطأ',
            self::Short => 'إجابة قصيرة',
            self::Essay => 'مقالي',
        };
    }

    /** الأنواع التي تحتاج خيارات معروضة للطالب. */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Mcq, self::Checkbox], true);
    }

    /** هل يُصحح هذا النوع آلياً عند التسليم؟ */
    public function isAutoGraded(): bool
    {
        return $this !== self::Essay;
    }
}
