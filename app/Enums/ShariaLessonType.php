<?php

namespace App\Enums;

enum ShariaLessonType: string
{
    case Lesson = 'lesson';
    case Lecture = 'lecture';

    public function label(): string
    {
        return match ($this) {
            self::Lesson => 'درس',
            self::Lecture => 'محاضرة',
        };
    }
}
