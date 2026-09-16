<?php

namespace App\Enums;

/**
 * مستوى تفاصيل عنصر «التسميع مع المعلم»:
 * - summary: حكم عام على الحفظ (تسميع جديد/مراجعة).
 * - detailed: تقييم كلمة بكلمة مع نسبة إتقان وإحصاءات أخطاء (استماع مع المعلم).
 */
enum QuranTeacherTimelineDetailLevel: string
{
    case Summary = 'summary';
    case Detailed = 'detailed';
}
