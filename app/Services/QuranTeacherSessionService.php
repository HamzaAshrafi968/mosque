<?php

namespace App\Services;

use App\Enums\QuranTeacherSessionType;
use App\Models\Student;
use App\Models\User;
use App\Support\QuranTeacherSessionTypeOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * «التسميع مع المعلم» — طبقة توجيه أنواع الجلسة الموحدة.
 *
 * تحدد الأنواع المتاحة للفاعل (مدير/معلم) ورابط الواجهة المناسب لكل نوع:
 *   new/revision → QuranRecitationSession (نموذج التسميع)
 *   listening    → QuranReviewSession (الاستماع التفصيلي)
 *
 * لا تحمل أي منطق حفظ — يبقى الحفظ في الـControllers المختصة
 * (QuranTasmeeController / QuranReviewController) كما هو.
 */
class QuranTeacherSessionService
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    /**
     * الخيارات المتاحة للفاعل على طالب محدد، بالترتيب المعروض في الواجهة.
     *
     * @return Collection<int, QuranTeacherSessionTypeOption>
     */
    public function typesFor(User $actor, Student $student, string $routePrefix): Collection
    {
        $options = collect();

        if ($this->authorization->can($actor, 'quran.tasmee.create', $student)) {
            foreach ([QuranTeacherSessionType::NewRecitation, QuranTeacherSessionType::Revision] as $tasmeeType) {
                $options->push(new QuranTeacherSessionTypeOption(
                    type: $tasmeeType,
                    url: route($routePrefix.'quran.tasmee.create', [
                        'student_id' => $student->id,
                        'type' => $tasmeeType->value,
                    ]),
                ));
            }
        }

        if ($this->authorization->can($actor, 'quran_review.create', $student)
            && Route::has($routePrefix.'quran-review.create')) {
            $options->push(new QuranTeacherSessionTypeOption(
                type: QuranTeacherSessionType::Listening,
                url: route($routePrefix.'quran-review.create', ['student_id' => $student->id]),
            ));
        }

        return $options;
    }
}
