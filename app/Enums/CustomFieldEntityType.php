<?php

namespace App\Enums;

enum CustomFieldEntityType: string
{
    case Student = 'student';
    case Teacher = 'teacher';
    case Hafiz = 'hafiz';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'الطلاب',
            self::Teacher => 'الأساتذة',
            self::Hafiz => 'الحفاظ',
        };
    }
}
