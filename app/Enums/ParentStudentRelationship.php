<?php

namespace App\Enums;

enum ParentStudentRelationship: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'أب',
            self::Mother => 'أم',
            self::Guardian => 'ولي أمر',
            self::Other => 'أخرى',
        };
    }
}
