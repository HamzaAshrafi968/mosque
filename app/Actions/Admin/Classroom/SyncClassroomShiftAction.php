<?php

namespace App\Actions\Admin\Classroom;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Services\EnrollmentService;
use App\Services\ScheduleConflictService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * نقل الصف إلى دوام (أو فصله عن الدوام) مع كل ما يتبعه: شعبه وطلاب شعبه
 * وجداوله، حتى تبقى بيانات الدوام متطابقة (صف/شعبة/طالب/حصة في الدوام نفسه).
 *
 * قبل النقل يُفحص تعارض المعلمين: حصص الصف المنقولة تُقارن بحصص المعلم
 * الثابتة، لأن نقل الحصص إلى دوام آخر قد يجعل المعلم في مكانين معاً.
 * كما يُرفض نقل صف فيه طلاب إلى دوام مخصص لجنس مختلف.
 */
class SyncClassroomShiftAction
{
    public function __construct(
        private readonly ScheduleConflictService $conflicts,
        private readonly EnrollmentService $enrollment,
    ) {}

    public function execute(Classroom $classroom, ?string $studySessionId): void
    {
        $sectionIds = Section::query()
            ->withoutGlobalScope('study_session')
            ->where('classroom_id', $classroom->id)
            ->pluck('id');

        if ($studySessionId !== null) {
            $messages = [];

            foreach ($this->conflicts->classroomShiftConflicts($classroom) as $conflict) {
                $messages[$conflict['field']][] = $conflict['message'];
            }

            if ($messages !== []) {
                throw ValidationException::withMessages($messages);
            }

            if ($sectionIds->isNotEmpty()) {
                $this->enrollment->assertStudentsMatchSessionGender(
                    $studySessionId,
                    Student::query()
                        ->withoutGlobalScope('study_session')
                        ->whereIn('section_id', $sectionIds)
                        ->pluck('gender')
                );
            }
        }

        DB::transaction(function () use ($classroom, $studySessionId, $sectionIds) {
            $classroom->update(['study_session_id' => $studySessionId]);

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
