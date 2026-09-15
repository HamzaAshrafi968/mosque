<?php

namespace App\Actions\Admin\Classroom;

use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * نقل الصف إلى دوام (أو فصله عن الدوام) مع كل ما يتبعه: شعبه وطلاب شعبه
 * وجداوله، حتى تبقى بيانات الدوام متطابقة (صف/شعبة/طالب/حصة في الدوام نفسه).
 */
class SyncClassroomShiftAction
{
    public function execute(Classroom $classroom, ?string $studySessionId): void
    {
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
