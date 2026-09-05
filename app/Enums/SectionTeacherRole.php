<?php

namespace App\Enums;

enum SectionTeacherRole: string
{
    case Lead = 'lead';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'معلم أساسي',
            self::Assistant => 'معلم مساعد',
        };
    }
}
