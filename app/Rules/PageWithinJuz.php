<?php

namespace App\Rules;

use App\Support\QuranJuzMap;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * يتحقق أن الصفحة داخل نطاق الجزء المحدد (مثال: الجزء ١ = صفحات ١–٢١،
 * الجزء ٣٠ = صفحات ٥٨٢–٦٠٤).
 */
class PageWithinJuz implements ValidationRule
{
    public function __construct(private readonly int|string|null $juz) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $juz = (int) $this->juz;

        if ($juz < 1 || $juz > QuranJuzMap::TOTAL_JUZ) {
            return;
        }

        $range = QuranJuzMap::pageRange($juz);

        if ((int) $value < $range['from'] || (int) $value > $range['to']) {
            $fail("الصفحة {$value} خارج نطاق الجزء {$juz} (صفحات {$range['from']}–{$range['to']})");
        }
    }
}
