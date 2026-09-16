<?php

namespace App\Enums;

enum QuranListeningTestResult: string
{
    case Pass = 'pass';
    case Fail = 'fail';

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'ناجح',
            self::Fail => 'يحتاج إعادة',
        };
    }
}
