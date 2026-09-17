<?php

namespace App\Actions\Admin\Classroom;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Services\ScheduleConflictService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * نقل الصف إلى دوام (أو فصله عن الدوام) مع كل ما يتبعه: شعبه وطلاب شعبه
 * وجداوله، حتى تبقى بيانات الدوام متطابقة (صف/شعبة/طالب/حصة في الدوام نفسه).
 *
 * قبل النقل يُفحص تعارض المعلمين: حصص الصف المنقولة تُقارن بحصص المعلم
 * الثابتة، لأن نقل الحصص إلى دوام آخر قد يجعل المعلم في مكانين معاً.
 */
class SyncClassroomShiftAction
{
    public function __construct(private readonly ScheduleConflictService $conflicts) {}

    public function execute(Classroom $classroom, ?string $studySessionId): void
    {
        if ($studySessionId !== null) {
            $messages = [];

            foreach ($this->conflicts->classroomShiftConflicts($classroom) as $conflict) {
                $messages[$conflict['field']][] = $conflict['message'];
            }

            if ($messages !== []) {
                throw ValidationException::withMessages($messages);
            }
        }

        DB::transaction(function () use ($classroom, $studySessionId) {
            $classroom->update(['study_session_id' => $studySessionId]);

            $sectionIds = Section::query()
                ->withoutGlobalScope('study_session')
                ->where('classroom_id', $classroom->id)
                ->pluck('id');

            Section::query()
                ->withoutGlobalScope('study_session')
                ->where('classroom_id', $classroom->id)
                ->update(['study_session_id' => $studySessionId]);

            if ($sectionIds->isNotEmpty()) {
                Student::query()
                    ->withoutGlobalScope('study_session')
                    ->whereIn('section_id', $sectionIds)
                    ->update(['study_session_id' => $studySessionId]);
            }

            Schedule::query()
                ->withoutGlobalScope('study_session')
                ->where(function ($query) use ($classroom, $sectionIds) {
                    $query->where('classroom_id', $classroom->id);

                    if ($sectionIds->isNotEmpty()) {
                        $query->orWhereIn('section_id', $sectionIds);
                    }
                })
                ->update(['study_session_id' => $studySessionId]);
        });
    }
}
