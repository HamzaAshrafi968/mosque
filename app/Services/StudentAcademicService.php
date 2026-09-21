<?php

namespace App\Services;

use App\Enums\AnnouncementAudience;
use App\Enums\ExamStatus;
use App\Enums\GradeStatus;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Homework;
use App\Models\Schedule;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Read-side helpers shared by the parent/guardian and student portals.
 *
 * Every method accepts an explicit Student so callers enforce their own scope
 * guard first (guardian: parent_students link; student: students.user_id).
 * Percentages are always derived from attendance_records (never stored).
 */
class StudentAcademicService
{
    public function __construct(private readonly AttendanceMetricService $attendance) {}

    /** Attendance history (date desc) with status + note per record. */
    public function attendanceHistory(Student $student): Collection
    {
        return AttendanceRecord::query()
            ->with(['session:id,date,section_id', 'session.section:id,name'])
            ->where('student_id', $student->id)
            ->orderByDesc('date')
            ->get()
            ->map(fn (AttendanceRecord $record) => [
                'date' => $record->session?->date?->toDateString(),
                'section' => $record->session?->section?->name,
                'status' => $record->status,
                'note' => $record->note,
            ]);
    }

    /** @return array{present:int,absent:int,late:int,excused:int,total:int,attended:int,percentage:?float} */
    public function attendanceSummary(Student $student, ?string $from = null, ?string $to = null): array
    {
        return $this->attendance->statsForStudent($student->id, $from, $to);
    }

    /** Teachers relevant to the student (assigned to the section or scheduled). */
    public function teachers(Student $student): Collection
    {
        $rows = collect();

        if ($student->section_id) {
            $student->section->assignedTeachers()
                ->where('section_teachers.status', 'active')
                ->where('teachers.is_active', true)
                ->get(['teachers.id', 'teachers.name', 'teachers.gender', 'teachers.phone', 'teachers.specialty', 'teachers.photo'])
                ->each(fn ($teacher) => $rows->push(['teacher' => $teacher, 'subject' => null]));
        }

        Schedule::query()
            ->activeOn(now())
            ->where('section_id', $student->section_id)
            ->with(['subject:id,name', 'teacher:id,name,photo'])
            ->orderBy('day_of_week')
            ->get()
            ->each(fn (Schedule $schedule) => $schedule->teacher
                ? $rows->push(['teacher' => $schedule->teacher, 'subject' => $schedule->subject])
                : null);

        return $rows->unique(fn ($row) => $row['teacher']->id.'-'.($row['subject']?->id ?? ''));
    }

    /** Subjects studied by the student's section with their teacher. */
    public function subjects(Student $student): Collection
    {
        return Schedule::query()
            ->activeOn(now())
            ->where('section_id', $student->section_id)
            ->with(['subject:id,name', 'teacher:id,name,photo', 'section:id,name'])
            ->orderBy('day_of_week')
            ->get()
            ->map(fn (Schedule $schedule) => [
                'subject' => $schedule->subject,
                'teacher' => $schedule->teacher,
                'section' => $schedule->section,
            ])
            ->filter(fn ($row) => $row['subject'] !== null)
            ->unique('subject.id')
            ->values();
    }

    /** Upcoming exams for the student's section (future dates, published only). */
    public function upcomingExams(Student $student): Collection
    {
        return Exam::query()
            ->with(['subject:id,name', 'section:id,name'])
            ->targetsStudent($student)
            ->whereIn('status', [ExamStatus::Published, ExamStatus::Closed])
            ->whereDate('exam_date', '>=', today())
            ->orderBy('exam_date')
            ->get();
    }

    /**
     * Published (approved) grades for the student; drafts and submitted rows
     * are not part of what students/guardians may see until approval.
     */
    public function publishedGrades(Student $student): Collection
    {
        return Grade::query()
            ->with(['exam:id,title,exam_date,total_marks,pass_marks,subject_id', 'exam.subject:id,name'])
            ->where('student_id', $student->id)
            ->where('status', GradeStatus::Approved)
            ->latest('updated_at')
            ->get()
            ->map(fn (Grade $grade) => [
                'grade' => $grade,
                'percentage' => $grade->exam && $grade->exam->total_marks > 0
                    ? round(((float) $grade->score / (float) $grade->exam->total_marks) * 100, 1)
                    : null,
            ]);
    }

    /** Homeworks for the student's class/section with their own submission. */
    public function homeworks(Student $student): Collection
    {
        return Homework::query()
            ->with([
                'subject:id,name',
                'teacher:id,name,photo',
                'submissions' => fn ($q) => $q->where('student_id', $student->id),
            ])
            ->where('classroom_id', $student->classroom_id)
            ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $student->section_id))
            ->latest()
            ->get()
            ->map(fn (Homework $homework) => [
                'homework' => $homework,
                'submission' => $homework->submissions->first(),
            ]);
    }

    /**
     * Announcements relevant to a student: general announcements plus those
     * targeting their classroom or every classroom (guardians additionally
     * receive the `guardians` audience). Announcements targeted to another
     * دوام are hidden; shift-less announcements stay visible to everyone.
     */
    public function announcements(Student $student, bool $guardianMode = false): Collection
    {
        return Announcement::query()
            ->whereNotNull('published_at')
            ->notExpired()
            ->where(function ($q) use ($student) {
                $q->whereNull('study_session_id');

                if ($student->study_session_id) {
                    $q->orWhere('study_session_id', $student->study_session_id);
                }
            })
            ->where(function ($q) use ($student, $guardianMode) {
                $q->where('audience', AnnouncementAudience::All)
                    ->orWhere('audience', AnnouncementAudience::Classrooms);

                if ($guardianMode) {
                    $q->orWhere('audience', AnnouncementAudience::Guardians);
                }

                $q->orWhere(function ($q) use ($student) {
                    $q->where('audience', AnnouncementAudience::Classroom)
                        ->where('classroom_id', $student->classroom_id);
                });
            })
            ->with('author:id,name,photo')
            ->latest('published_at')
            ->get();
    }

    /** Quran review sessions summary for a student (mastery + points). */
    public function quranStats(Student $student): array
    {
        return [
            'sessions' => $student->quranReviewSessions()->count(),
            'points' => $student->totalPoints(),
        ];
    }

    /**
     * بطاقات لوحة ولي الأمر لكل الأبناء باستعلامات مجمّعة بدل 4 استعلامات
     * لكل ابن (الحضور + الامتحانات + الواجبات + الدرجات).
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array{student: Student, attendance: array, upcomingExams: Collection, pendingHomeworks: int, publishedGrades: int}>
     */
    public function dashboardCards(Collection $students): Collection
    {
        if ($students->isEmpty()) {
            return collect();
        }

        $ids = $students->pluck('id')->all();

        // 1) الحضور — مجمّع مسبقاً في AttendanceMetricService.
        $attendance = $this->attendance->studentStats($students);

        // 2) الامتحانات القادمة — استعلام واحد ثم مطابقة الاستهداف في الذاكرة.
        $exams = Exam::query()
            ->with(['subject:id,name', 'section:id,name', 'classrooms:id,name'])
            ->whereIn('status', [ExamStatus::Published, ExamStatus::Closed])
            ->whereDate('exam_date', '>=', today())
            ->orderBy('exam_date')
            ->get();

        // 3) الواجبات — استعلام واحد مع تسليمات كل الأبناء.
        $homeworks = Homework::query()
            ->with([
                'subject:id,name',
                'teacher:id,name,photo',
                'submissions' => fn ($q) => $q->whereIn('student_id', $ids),
            ])
            ->whereIn('classroom_id', $students->pluck('classroom_id')->filter()->unique()->all())
            ->latest()
            ->get();

        // 4) الدرجات المعتمدة — استعلام واحد مجمّع بالطالب.
        $grades = Grade::query()
            ->with(['exam:id,title,exam_date,total_marks,pass_marks,subject_id', 'exam.subject:id,name'])
            ->whereIn('student_id', $ids)
            ->where('status', GradeStatus::Approved)
            ->latest('updated_at')
            ->get()
            ->groupBy('student_id');

        return $students->map(function (Student $student) use ($attendance, $exams, $homeworks, $grades) {
            $studentHomeworks = $homeworks->filter(fn (Homework $homework) => $homework->classroom_id === $student->classroom_id
                && ($homework->section_id === null || $homework->section_id === $student->section_id));

            $pending = $studentHomeworks->filter(function (Homework $homework) use ($student) {
                $submission = $homework->submissions->firstWhere('student_id', $student->id);

                return $submission !== null && $submission->status === 'pending';
            })->count();

            return [
                'student' => $student,
                'attendance' => $attendance->get($student->id, []),
                'upcomingExams' => $exams->filter(fn (Exam $exam) => $this->examTargetsStudent($exam, $student))->values(),
                'pendingHomeworks' => $pending,
                'publishedGrades' => $grades->get($student->id, collect())->count(),
            ];
        })->values();
    }

    /** مطابقة استهداف الامتحان للطالب في الذاكرة (نفس منطق Exam::targetsStudent). */
    private function examTargetsStudent(Exam $exam, Student $student): bool
    {
        $classroomIds = $exam->classrooms->pluck('id')->all();

        if ($classroomIds === [] && $exam->classroom_id !== null) {
            $classroomIds = [$exam->classroom_id];
        }

        if (in_array($student->classroom_id, $classroomIds, true)) {
            return $exam->section_id === null || $exam->section_id === $student->section_id;
        }

        return $exam->classroom_id === null
            && $classroomIds === []
            && $exam->study_session_id !== null
            && $exam->study_session_id === $student->study_session_id;
    }
}
