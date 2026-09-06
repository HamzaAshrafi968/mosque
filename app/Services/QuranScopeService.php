<?php

namespace App\Services;

use App\Enums\SectionStudentStatus;
use App\Models\FaithMeeting;
use App\Models\FaithMeetingStudent;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Quran-program student scope for teachers (spec §16).
 *
 * A teacher may only access:
 *   - students in their assigned sections
 *   - students they already supervise (tasmee', qualifying/ijazah evals,
 *     monthly hafiz exams, faith meetings they organize)
 *
 * Every scope decision is enforced server-side in the controllers.
 */
class QuranScopeService
{
    /** All student ids a teacher may manage in the quran programs. */
    public function studentIdsFor(Teacher $teacher): array
    {
        $ids = collect();

        // 1. Students of the sections the teacher manages (explicit + timetable).
        $ids = $ids->merge(
            Student::query()
                ->whereHas('activeEnrollment', function (Builder $q) use ($teacher) {
                    $q->where('section_students.status', SectionStudentStatus::Active)
                        ->whereIn('section_students.section_id', $teacher->manageableSectionIds());
                })
                ->pluck('id')
        );

        // 2. Students already supervised through tasmee'.
        $ids = $ids->merge($teacher->quranRecitationSessions()->pluck('student_id'));

        // 3. Students evaluated by this teacher in both programs.
        $ids = $ids->merge($teacher->qualifyingEvaluations()->pluck('student_id'));
        $ids = $ids->merge($teacher->ijazahEvaluations()->pluck('student_id'));

        // 4. Students attached to faith meetings organized by this teacher.
        $meetingIds = $teacher->supervisedMeetings()->pluck('id')
            ->merge($teacher->coRunMeetings()->pluck('id'))
            ->unique()
            ->values();

        if ($meetingIds->isNotEmpty()) {
            $ids = $ids->merge(
                FaithMeetingStudent::query()
                    ->whereIn('meeting_id', $meetingIds)
                    ->pluck('student_id')
            );
        }

        // 5. Students whose monthly hafiz exam this teacher supervises.
        $ids = $ids->merge($teacher->supervisedExams()->pluck('student_id'));

        return $ids->unique()->values()->all();
    }

    /** Students query restricted to the ids the teacher manages. */
    public function studentsQuery(Teacher $teacher): Builder
    {
        $ids = $this->studentIdsFor($teacher);

        return Student::query()->whereIn('id', $ids);
    }

    /** Student collection scoped to the teacher, ordered by name. */
    public function studentsFor(Teacher $teacher): Collection
    {
        return $this->studentsQuery($teacher)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function assertCanManageStudent(Teacher $teacher, Student $student): void
    {
        if (! in_array($student->id, $this->studentIdsFor($teacher), true)) {
            abort(403, 'لا تملك صلاحية الوصول لبيانات هذا الطالب');
        }
    }

    /** Faith meetings a teacher manages (organizer only). */
    public function meetingsQuery(Teacher $teacher): Builder
    {
        return FaithMeeting::query()
            ->where(function (Builder $q) use ($teacher) {
                $q->where('supervisor_id', $teacher->id)
                    ->orWhere('teacher_id', $teacher->id);
            });
    }
}
