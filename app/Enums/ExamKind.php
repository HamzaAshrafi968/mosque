<?php

namespace App\Enums;

enum ExamKind: string
{
    case Exam = 'exam';
    case Quiz = 'quiz';

    public function label(): string
    {
        return match ($this) {
            self::Exam => 'امتحان',
            self::Quiz => 'مذاكرة',
        };
    }
}
