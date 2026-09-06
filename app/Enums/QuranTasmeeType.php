<?php

namespace App\Enums;

enum QuranTasmeeType: string
{
    case New = 'new';
    case Revision = 'revision';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديد',
            self::Revision => 'مراجعة',
        };
    }
}
