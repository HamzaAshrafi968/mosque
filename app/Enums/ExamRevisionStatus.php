<?php

namespace App\Enums;

enum ExamRevisionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد التنفيذ',
            self::Completed => 'مكتمل',
            self::Approved => 'معتمد',
        };
    }
}
