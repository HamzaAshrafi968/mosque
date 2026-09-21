<?php

namespace App\Support;

/**
 * حماية ملفات CSV من Formula Injection: أي خلية نصية تبدأ بـ = + - @
 * أو tab/CR تُسبق بفاصلة عليا حتى لا تُنفَّذ كصيغة في Excel/Sheets.
 */
final class Csv
{
    public static function safe(string|int|float|null $value): string|int|float|null
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
