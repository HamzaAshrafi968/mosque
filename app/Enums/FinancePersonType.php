<?php

namespace App\Enums;

enum FinancePersonType: string
{
    case Student = 'student';
    case Teacher = 'teacher';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'طالب',
            self::Teacher => 'أستاذ',
        };
    }
}
