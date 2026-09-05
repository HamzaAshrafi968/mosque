<?php

namespace App\Enums;

enum CustomFieldEntityType: string
{
    case Student = 'student';
    case Teacher = 'teacher';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'الطلاب',
            self::Teacher => 'الأساتذة',
        };
    }
}
