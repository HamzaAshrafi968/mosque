<?php

namespace App\Services;

use App\Enums\ShariaAttendanceStatus;
use App\Enums\ShariaMemorizationStatus;
use App\Models\Section;
use App\Models\ShariaCourse;
use App\Models\ShariaCourseAttendance;
use App\Models\ShariaCourseLesson;
use App\Models\ShariaCourseStudent;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShariaCourseService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EnrollmentService $enrollment,
    ) {}

    /**
     * Upsert the attendance marks of a lesson (or a general daily attendance)
     * inside one transaction.
     *
     * @param  array<string, array{status?: string, notes?: string|null}|string>  $marks
     * @return int number of saved rows
     */
    public function saveAttendance(ShariaCourse $course, ?ShariaCourseLesson $lesson, string $date, array $marks, User $actor): int
    {
        return DB::transaction(function () use ($course, $lesson, $date, $marks, $actor) {
            $studentIds = $course->students()->pluck('id');
            $saved = 0;

            foreach ($marks as $studentId => $mark) {
                $status = is_array($mark) ? ($mark['status'] ?? null) : $mark;
                $notes = is_array($mark) ? ($mark['notes'] ?? null) : null;

                if (! $studentIds->contains($studentId)) {
                    continue;
                }

                $allowed = array_column(ShariaAttendanceStatus::cases(), 'value');

                if (! in_array($status, $allowed, true)) {
                    continue;
                }

                if ($status === ShariaAttendanceStatus::Excused->value && blank($notes)) {
                    throw ValidationException::withMessages([
                        'attendance' => ['حالة «معذور» تتطلب إدخال ملاحظة'],
                    ]);
                }

                $identity = [
                    'tenant_id' => $course->tenant_id,
                    'course_id' => $course->id,
                    'lesson_id' => $lesson?->id,
                    'student_id' => $studentId,
                ];

                if ($lesson === null) {
                    $identity['date'] = $date;
                }

                ShariaCourseAttendance::updateOrCreate($identity, [
                    'date' => $date,
                    'status' => $status,
                    'notes' => $notes,
                    'recorded_by' => $actor->id,
                ]);

                $saved++;
            }

            $this->audit->log('sharia_course.attendance_saved', 'sharia_course', $course->id, $course->tenant_id, after: [
                'lesson_id' => $lesson?->id,
                'date' => $date,
                'students' => $saved,
            ], actor: $actor);

            return $saved;
        });
    }

    /**
     * Attendance totals + percentage. Excused is excluded from the denominator
     * (same policy as the general attendance module).
     *
     * @return array{present: int, absent: int, late: int, excused: int, total: int, percentage: float|null}
     */
    public function attendanceSummary(ShariaCourse $course, ?ShariaCourseStudent $student = null): array
    {
        $query = ShariaCourseAttendance::query()->where('course_id', $course->id);

        if ($student) {
            $query->where('student_id', $student->id);
        }

        $counts = $query->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $summary = [
            'present' => (int) ($counts[ShariaAttendanceStatus::Present->value] ?? 0),
            'absent' => (int) ($counts[ShariaAttendanceStatus::Absent->value] ?? 0),
            'late' => (int) ($counts[ShariaAttendanceStatus::Late->value] ?? 0),
            'excused' => (int) ($counts[ShariaAttendanceStatus::Excused->value] ?? 0),
        ];
        $summary['total'] = array_sum($summary);

        $denominator = $summary['present'] + $summary['late'] + $summary['absent'];
        $summary['percentage'] = $denominator > 0
            ? round(($summary['present'] + $summary['late']) / $denominator * 100, 1)
            : null;

        return $summary;
    }

    /**
     * Batched attendanceSummary() for a roster: one grouped query for all
     * students instead of a query per student (the course report tab).
     *
     * @param  Collection<int, ShariaCourseStudent>  $students
     * @return Collection<string, array{present: int, absent: int, late: int, excused: int, total: int, percentage: float|null}>
     */
    public function attendanceSummaries(ShariaCourse $course, Collection $students): Collection
    {
        $counts = ShariaCourseAttendance::query()
            ->where('course_id', $course->id)
            ->whereIn('student_id', $students->pluck('id'))
            ->selectRaw('student_id, status, count(*) as total')
            ->groupBy('student_id', 'status')
            ->get()
            ->groupBy('student_id');

        return $students->mapWithKeys(function (ShariaCourseStudent $student) use ($counts) {
            $rows = $counts->get($student->id, collect())->pluck('total', 'status');

            $summary = [
                'present' => (int) ($rows[ShariaAttendanceStatus::Present->value] ?? 0),
                'absent' => (int) ($rows[ShariaAttendanceStatus::Absent->value] ?? 0),
                'late' => (int) ($rows[ShariaAttendanceStatus::Late->value] ?? 0),
                'excused' => (int) ($rows[ShariaAttendanceStatus::Excused->value] ?? 0),
            ];
            $summary['total'] = array_sum($summary);

            $denominator = $summary['present'] + $summary['late'] + $summary['absent'];
            $summary['percentage'] = $denominator > 0
                ? round(($summary['present'] + $summary['late']) / $denominator * 100, 1)
                : null;

            return [$student->id => $summary];
        });
    }

    /** The supervisor (any of them) or any lesson/lecture teacher may access the course. */
    public function assertCourseAccess(Teacher $teacher, ShariaCourse $course): void
    {
        if ($course->supervisors()->whereKey($teacher->id)->exists()) {
            return;
        }

        $teaches = $course->lessons()->where('teacher_id', $teacher->id)->exists();
        abort_unless($teaches, 403, 'لا تملك صلاحية الوصول لهذه الدورة');
    }

    /**
     * تسجيل طلاب موجودين (من جدول students) في الدورة — ينسخ بياناتهم
     * الأساسية ويتجاهل من هو مسجَّل سابقاً.
     *
     * @param  array<int, string>  $studentIds
     * @return int عدد الطلاب المضافين
     */
    public function syncEnrolledStudents(ShariaCourse $course, array $studentIds, User $actor): int
    {
        $studentIds = array_values(array_unique(array_filter($studentIds)));

        if ($studentIds === []) {
            return 0;
        }

        $existing = $course->students()
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id')
            ->all();

        $students = Student::withoutGlobalScopes(['tenant', 'study_session'])
            ->whereIn('id', array_diff($studentIds, $existing))
            ->where('tenant_id', $course->tenant_id)
            ->get();

        $added = 0;

        foreach ($students as $student) {
            $course->students()->create([
                'tenant_id' => $course->tenant_id,
                'student_id' => $student->id,
                'name' => $student->name,
                'phone' => $student->guardian_phone,
                'gender' => $student->gender,
                'birth_date' => $student->birth_date,
                'guardian_name' => $student->guardian_name,
                'guardian_phone' => $student->guardian_phone,
                'status' => ShariaCourseStudent::STATUS_ACTIVE,
            ]);

            $added++;
        }

        if ($added > 0) {
            $this->audit->log('sharia_course.students_enrolled', 'sharia_course', $course->id, $course->tenant_id, after: [
                'students' => $added,
            ], actor: $actor);
        }

        return $added;
    }

    /**
     * تسجيل طالب جديد: يُنشأ سجل طالب رسمي في جامع الدورة (يظهر في قائمة
     * الطلاب العادية) مع الصف/الشعبة اختيارياً، ثم يُربط بالدورة مباشرة.
     *
     * @param  array<string, mixed>  $data
     */
    public function registerNewStudent(ShariaCourse $course, array $data, User $actor): ShariaCourseStudent
    {
        return DB::transaction(function () use ($course, $data, $actor) {
            $section = null;

            if (filled($data['section_id'] ?? null)) {
                $section = Section::withoutGlobalScopes(['tenant', 'study_session'])
                    ->where('tenant_id', $course->tenant_id)
                    ->find($data['section_id']);

                if (! $section) {
                    throw ValidationException::withMessages([
                        'section_id' => ['الشعبة المختارة لا تنتمي لجامع الدورة'],
                    ]);
                }
            }

            $student = Student::create([
                'tenant_id' => $course->tenant_id,
                'study_session_id' => $section?->study_session_id,
                'classroom_id' => $section?->classroom_id ?? ($data['classroom_id'] ?? null),
                'section_id' => $section?->id,
                'name' => $data['name'],
                'gender' => $data['gender'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'guardian_name' => $data['guardian_name'] ?? null,
                'guardian_phone' => $data['guardian_phone'] ?? $data['phone'] ?? null,
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
            ]);

            if ($section) {
                $this->enrollment->enroll($student, $section);
            }

            $enrolled = $course->students()->create([
                'tenant_id' => $course->tenant_id,
                'student_id' => $student->id,
                'name' => $student->name,
                'phone' => $student->guardian_phone,
                'gender' => $student->gender,
                'birth_date' => $student->birth_date,
                'guardian_name' => $student->guardian_name,
                'guardian_phone' => $student->guardian_phone,
                'notes' => $student->notes,
                'status' => ShariaCourseStudent::STATUS_ACTIVE,
            ]);

            $this->audit->logModel('student.created', $student, actor: $actor);
            $this->audit->log('sharia_course.students_enrolled', 'sharia_course', $course->id, $course->tenant_id, after: [
                'students' => 1,
                'student_id' => $student->id,
            ], actor: $actor);

            return $enrolled;
        });
    }

    /**
     * يحل معرفات الطلاب النشطين في صفوف/شعب مختارة داخل جامع الدورة،
     * مع استثناء من هو مسجَّل مسبقاً فيها.
     *
     * @param  array<int, string|null>  $classroomIds
     * @param  array<int, string|null>  $sectionIds
     * @return array<int, string>
     */
    public function studentIdsForClasses(ShariaCourse $course, array $classroomIds, array $sectionIds): array
    {
        $classroomIds = array_values(array_unique(array_filter($classroomIds)));
        $sectionIds = array_values(array_unique(array_filter($sectionIds)));

        if ($classroomIds === [] && $sectionIds === []) {
            return [];
        }

        $enrolled = $course->students()->whereNotNull('student_id')->pluck('student_id')->all();

        return Student::withoutGlobalScopes(['tenant', 'study_session'])
            ->where('tenant_id', $course->tenant_id)
            ->where('status', 'active')
            ->where(function (Builder $query) use ($classroomIds, $sectionIds) {
                if ($classroomIds !== []) {
                    $query->whereIn('classroom_id', $classroomIds);
                }

                if ($sectionIds !== []) {
                    $query->orWhereIn('section_id', $sectionIds);
                }
            })
            ->whereNotIn('id', $enrolled)
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    /** تحديث حالة حفظ طالب في الدورة (مدير الجامع أو مشرف الدورة). */
    public function updateMemorization(
        ShariaCourseStudent $student,
        ?ShariaMemorizationStatus $status,
        ?string $notes,
        User $actor,
    ): void {
        $before = $student->getAttributes();

        $student->update([
            'memorization_status' => $status,
            'memorization_notes' => $notes,
            'memorization_updated_by' => $actor->id,
            'memorization_updated_at' => now(),
        ]);

        $this->audit->logModel('sharia_course.memorization_updated', $student, $before, actor: $actor);

        app(RewardPointAutoService::class)->awardForShariaMemorization($student, $actor);
    }

    /**
     * Data required by the attendance tab (lesson/day selector + roster + existing marks).
     *
     * @return array<string, mixed>
     */
    public function attendanceTabData(ShariaCourse $course, ?string $lessonId, string $date): array
    {
        if ($lessonId && ! $course->lessons()->whereKey($lessonId)->exists()) {
            $lessonId = null;
        }

        $existingQuery = ShariaCourseAttendance::query()
            ->where('course_id', $course->id)
            ->where('date', $date);

        if ($lessonId) {
            $existingQuery->where('lesson_id', $lessonId);
        } else {
            $existingQuery->whereNull('lesson_id');
        }

        $existing = $existingQuery->get();

        return [
            'lessons' => $course->lessons()->orderByDesc('date')->get(['id', 'title', 'date', 'type']),
            'selectedLessonId' => $lessonId,
            'attendanceDate' => $date,
            'students' => $course->students()->where('status', ShariaCourseStudent::STATUS_ACTIVE)->orderBy('name')->get(),
            'existing' => $existing->mapWithKeys(fn (ShariaCourseAttendance $row) => [$row->student_id => $row->status->value]),
            'existingNotes' => $existing->mapWithKeys(fn (ShariaCourseAttendance $row) => [$row->student_id => $row->notes]),
        ];
    }

    /** Courses supervised by the teacher or containing one of his lessons. */
    public function coursesFor(Teacher $teacher): Builder
    {
        return ShariaCourse::query()
            ->where(fn (Builder $query) => $query
                ->whereHas('supervisors', fn (Builder $supervisors) => $supervisors->whereKey($teacher->id))
                ->orWhereHas('lessons', fn (Builder $lessons) => $lessons->where('teacher_id', $teacher->id)));
    }
}
