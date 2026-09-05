<?php

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case Select = 'select';
    case Multiselect = 'multiselect';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'نص قصير',
            self::Textarea => 'نص طويل',
            self::Number => 'رقم',
            self::Date => 'تاريخ',
            self::Boolean => 'صح / خطأ',
            self::Select => 'قائمة اختيار',
            self::Multiselect => 'قائمة اختيار متعدد',
        };
    }

    /** Whether the field definition carries an options list. */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::Multiselect], true);
    }
}
