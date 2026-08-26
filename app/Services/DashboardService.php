<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\HomeworkSubmission;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public const TTL = 600;

    public static function key(string $tenantId, string $suffix): string
    {
        return "tenant:{$tenantId}:{$suffix}";
    }

    public static function flush(string $tenantId): void
    {
        Cache::forget(self::key($tenantId, 'dashboard_stats'));
        Cache::forget(self::key($tenantId, 'latest_announcements'));
    }

    public function stats(string $tenantId): array
    {
        return Cache::remember(self::key($tenantId, 'dashboard_stats'), self::TTL, function () {
            $today = now()->toDateString();

            $attendanceToday = Attendance::query()
                ->whereDate('date', $today)
                ->whereNotNull('student_id')
                ->selectRaw("
                    count(*) as total,
                    sum(case when status = 'present' then 1 else 0 end) as present,
                    sum(case when status = 'absent' then 1 else 0 end) as absent,
                    sum(case when status = 'late' then 1 else 0 end) as late
                ")
                ->first();

            $total = (int) ($attendanceToday->total ?? 0);

            $examStats = Grade::query()
                ->whereIn('grades.status', ['submitted', 'approved'])
                ->join('exams', 'exams.id', '=', 'grades.exam_id')
                ->selectRaw('
                    count(*) as total,
                    sum(case when grades.score >= coalesce(exams.pass_marks, exams.total_marks * 0.5) then 1 else 0 end) as passed,
                    sum(case when grades.score < coalesce(exams.pass_marks, exams.total_marks * 0.5) then 1 else 0 end) as failed
                ')
                ->first();

            $examTotal = (int) ($examStats->total ?? 0);

            $homeworkStats = HomeworkSubmission::query()
                ->where('homework_submissions.status', 'graded')
                ->whereNotNull('homework_submissions.grade')
                ->join('homeworks', 'homeworks.id', '=', 'homework_submissions.homework_id')
                ->selectRaw('
                    count(*) as total,
                    sum(case when homework_submissions.grade >= coalesce(homeworks.pass_marks, 50) then 1 else 0 end) as passed,
                    sum(case when homework_submissions.grade < coalesce(homeworks.pass_marks, 50) then 1 else 0 end) as failed
                ')
                ->first();

            $homeworkTotal = (int) ($homeworkStats->total ?? 0);

            return [
                'students_count' => Student::active()->count(),
                'male_students_count' => Student::active()->where('gender', 'male')->count(),
                'female_students_count' => Student::active()->where('gender', 'female')->count(),
                'teachers_count' => Teacher::where('is_active', true)->count(),
                'classrooms_count' => Classroom::count(),
                'sections_count' => Section::count(),
                'attendance_present_today' => (int) ($attendanceToday->present ?? 0),
                'attendance_absent_today' => (int) ($attendanceToday->absent ?? 0),
                'attendance_late_today' => (int) ($attendanceToday->late ?? 0),
                'attendance_rate_today' => $total > 0
                    ? round(((int) $attendanceToday->present / $total) * 100, 1)
                    : null,
                'exam_graded_count' => $examTotal,
                'exam_passed_count' => (int) ($examStats->passed ?? 0),
                'exam_failed_count' => (int) ($examStats->failed ?? 0),
                'exam_pass_rate' => $examTotal > 0
                    ? round(((int) $examStats->passed / $examTotal) * 100, 1)
                    : null,
                'homework_graded_count' => $homeworkTotal,
                'homework_passed_count' => (int) ($homeworkStats->passed ?? 0),
                'homework_failed_count' => (int) ($homeworkStats->failed ?? 0),
                'homework_pass_rate' => $homeworkTotal > 0
                    ? round(((int) $homeworkStats->passed / $homeworkTotal) * 100, 1)
                    : null,
            ];
        });
    }

    public function latestAnnouncements(string $tenantId, int $limit = 5)
    {
        return Cache::remember(self::key($tenantId, 'latest_announcements'), self::TTL, function () use ($limit) {
            return Announcement::query()
                ->with('author:id,name')
                ->latest('published_at')
                ->limit($limit)
                ->get();
        });
    }
}
