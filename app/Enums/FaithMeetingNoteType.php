<?php

namespace App\Enums;

enum FaithMeetingNoteType: string
{
    case Note = 'note';
    case Suggestion = 'suggestion';
    case ActionItem = 'action_item';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'ملاحظة',
            self::Suggestion => 'اقتراح',
            self::ActionItem => 'إجراء',
        };
    }
}
