<?php

namespace App\Support;

use App\Models\Classroom;
use Illuminate\Validation\ValidationException;

/**
 * توحيد نطاق الاختبار (ويب + API):
 * - «دوام كامل»: بلا صفوف + study_session_id.
 * - «صفوف محددة»: صف أو عدة صفوف عبر classroom_ids (أو classroom_id القديم)،
 *   مع شعبة اختيارية عند اختيار صف واحد فقط.
 */
class ExamTargeting
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{classroom_ids: array<int, string>, study_session_id: ?string, section_id: ?string}
     *
     * @throws ValidationException
     */
    public static function resolve(array $data): array
    {
        $classroomIds = collect($data['classroom_ids'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->unique()
            ->values()
            ->all();

        if ($classroomIds === [] && filled($data['classroom_id'] ?? null)) {
            $classroomIds = [$data['classroom_id']];
        }

        $studySessionId = filled($data['study_session_id'] ?? null) ? $data['study_session_id'] : null;
        $sectionId = filled($data['section_id'] ?? null) ? $data['section_id'] : null;

        if ($classroomIds === [] && $studySessionId === null) {
            throw ValidationException::withMessages([
                'classroom_ids' => 'حدد الدوام كاملاً أو صفاً واحداً على الأقل',
            ]);
        }

        if (count($classroomIds) > 1 && $sectionId !== null) {
            throw ValidationException::withMessages([
                'section_id' => 'لا يمكن تحديد شعبة عند اختيار أكثر من صف',
            ]);
        }

        if ($classroomIds !== []) {
            // العزل بالجامع مفروض عبر Global Scope، ويجب أن تخص كل الصفوف الدوام المحدد.
            $classrooms = Classroom::query()
                ->withoutGlobalScope('study_session')
                ->whereIn('id', $classroomIds)
                ->get(['id', 'study_session_id']);

            if ($classrooms->count() !== count($classroomIds)) {
                throw ValidationException::withMessages([
                    'classroom_ids' => 'أحد الصفوف المختارة غير موجود',
                ]);
            }

            if ($studySessionId !== null && $classrooms->contains(
                fn (Classroom $classroom) => $classroom->study_session_id !== null
                    && $classroom->study_session_id !== $studySessionId
            )) {
                throw ValidationException::withMessages([
                    'classroom_ids' => 'اختر صفوف الدوام المحدد فقط',
                ]);
            }
        }

        return [
            'classroom_ids' => $classroomIds,
            'study_session_id' => $studySessionId,
            'section_id' => count($classroomIds) === 1 ? $sectionId : null,
        ];
    }
}
