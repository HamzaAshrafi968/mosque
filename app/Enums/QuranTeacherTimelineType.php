<?php

namespace App\Enums;

/**
 * نوع عنصر «التسميع مع المعلم»: يوحّد عرض جلسات التسميع (حكم عام)
 * وجلسات الاستماع التفصيلي (تقييم كلمة بكلمة) في سجل واحد،
 * مع بقاء الكيانين مستقلين في قاعدة البيانات.
 */
enum QuranTeacherTimelineType: string
{
    case RecitationNew = 'recitation_new';
    case RecitationRevision = 'recitation_revision';
    case TeacherListening = 'teacher_listening';

    public function label(): string
    {
        return match ($this) {
            self::RecitationNew => 'تسميع جديد',
            self::RecitationRevision => 'تسميع مراجعة',
            self::TeacherListening => 'استماع مع المعلم',
        };
    }

    public function isRecitation(): bool
    {
        return $this !== self::TeacherListening;
    }
}
