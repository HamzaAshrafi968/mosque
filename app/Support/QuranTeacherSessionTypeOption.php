<?php

namespace App\Support;

use App\Enums\QuranTeacherSessionType;

/**
 * خيار واحد في بوابة «التسميع مع المعلم» الموحدة:
 * نوع الجلسة ورابط الواجهة (Workflow) المناسب له.
 */
final readonly class QuranTeacherSessionTypeOption
{
    public function __construct(
        public QuranTeacherSessionType $type,
        public string $url,
    ) {}
}
