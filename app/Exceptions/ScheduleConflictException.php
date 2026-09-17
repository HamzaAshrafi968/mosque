<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * تعارض في الجدول الدراسي (تداخل حصص أو انشغال معلم/شعبة/طالب).
 *
 * يرث ValidationException حتى تُعرض الرسالة بجانب الحقل المتسبب في النموذج
 * ويُعاد المستخدم إلى النموذج تلقائياً في واجهات الويب.
 */
class ScheduleConflictException extends ValidationException
{
    /** أول رسالة مكتشفة (للواجهات المختصرة). */
    public function firstMessage(): string
    {
        return collect($this->errors())->flatten()->first() ?? 'يوجد تعارض في الجدول';
    }
}
