<?php

namespace App\Enums;

/**
 * أنواع «التسميع مع المعلم» في بوابة التسجيل الموحدة.
 *
 * «تسميع حفظ جديد» و«تسميع مراجعة» يوجّهان إلى QuranRecitationSession
 * (نموذج التسميع الحالي)، و«استماع وتقييم تفصيلي» إلى QuranReviewSession —
 * دون أي دمج بين الكيانين في الـDomain.
 */
enum QuranTeacherSessionType: string
{
    case NewRecitation = 'new';

    case Revision = 'revision';

    case Listening = 'listening';

    public function label(): string
    {
        return match ($this) {
            self::NewRecitation => 'تسميع حفظ جديد',
            self::Revision => 'تسميع مراجعة',
            self::Listening => 'استماع وتقييم تفصيلي',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NewRecitation => 'تسميع حفظ جديد ضمن الدفعة الحالية — النطاق المسموح والصفحة المقترحة تُحسب تلقائياً.',
            self::Revision => 'تسميع مراجعة لمحفوظ سابق — خارج تغطية دفعة الحفظ الجديد.',
            self::Listening => 'استماع كلمة بكلمة مع تقييم تفصيلي: الأخطاء، التجويد، التردد، والإتقان.',
        };
    }
}
