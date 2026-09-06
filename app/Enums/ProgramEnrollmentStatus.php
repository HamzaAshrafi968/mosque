<?php

namespace App\Enums;

enum ProgramEnrollmentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'نشط',
            self::Completed => 'مكتمل',
        };
    }
}
