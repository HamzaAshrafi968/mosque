<?php

namespace App\Enums;

enum RoleScope: string
{
    case Global = 'global';
    case Mosque = 'mosque';
    case ClassScope = 'class';
    case Section = 'section';
    case Own = 'own';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'شامل (كل الجوامع)',
            self::Mosque => 'الجامع الخاص',
            self::ClassScope => 'صفوف محددة',
            self::Section => 'شعب محددة',
            self::Own => 'خاص بالمستخدم فقط',
        };
    }
}
