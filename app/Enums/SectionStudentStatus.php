<?php

namespace App\Enums;

enum SectionStudentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Transferred = 'transferred';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'نشط',
            self::Inactive => 'غير نشط',
            self::Transferred => 'منقول',
            self::Completed => 'أكمل',
        };
    }
}
