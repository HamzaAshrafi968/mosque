<?php

namespace App\Services;

use App\Enums\AnnouncementAudience;
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
                ->get(['teachers.id', 'teachers.name', 'teachers.gender', 'teachers.phone', 'teachers.specialty'])
                ->each(fn ($teacher) => $rows->push(['teacher' => $teacher, 'subject' => null]));
        }

        Schedule::query()
            ->where('section_id', $student->section_id)
            ->with(['subject:id,name', 'teacher:id,name'])
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
            ->where('section_id', $student->section_id)
            ->with(['subject:id,name', 'teacher:id,name', 'section:id,name'])
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

    /** Upcoming exams for the student's section (future dates). */
    public function upcomingExams(Student $student): Collection
    {
        return Exam::query()
            ->with(['subject:id,name', 'section:id,name'])
            ->where('section_id', $student->section_id)
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
                'teacher:id,name',
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
     * targeting their classroom (guardians additionally receive the
     * `guardians` audience).
     */
    public function announcements(Student $student, bool $guardianMode = false): Collection
    {
        return Announcement::query()
            ->whereNotNull('published_at')
            ->where(function ($q) use ($student, $guardianMode) {
                $q->where('audience', AnnouncementAudience::All);

                if ($guardianMode) {
                    $q->orWhere('audience', AnnouncementAudience::Guardians);
                }

                $q->orWhere(function ($q) use ($student) {
                    $q->where('audience', AnnouncementAudience::Classroom)
                        ->where('classroom_id', $student->classroom_id);
                });
            })
            ->with('author:id,name')
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
}
