<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $mosques = Tenant::query()
            ->withCount('users')
            ->orderBy('created_at')
            ->get();

        // إحصاءات كل جامع باستعلامات مجمّعة (بدل 4 استعلامات لكل جامع).
        $studentCounts = Student::withoutGlobalScope('tenant')
            ->selectRaw('tenant_id, count(*) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $teacherCounts = Teacher::withoutGlobalScope('tenant')
            ->selectRaw('tenant_id, count(*) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $classroomCounts = Classroom::withoutGlobalScope('tenant')
            ->selectRaw('tenant_id, count(*) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $pendingCounts = Grade::withoutGlobalScope('tenant')
            ->where('grades.status', 'submitted')
            ->selectRaw('tenant_id, count(*) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $mosques->each(function (Tenant $mosque) use ($studentCounts, $teacherCounts, $classroomCounts, $pendingCounts) {
            $mosque->setAttribute('students_count', (int) ($studentCounts[$mosque->id] ?? 0));
            $mosque->setAttribute('teachers_count', (int) ($teacherCounts[$mosque->id] ?? 0));
            $mosque->setAttribute('classrooms_count', (int) ($classroomCounts[$mosque->id] ?? 0));
            $mosque->setAttribute('pending_approvals', (int) ($pendingCounts[$mosque->id] ?? 0));
        });

        $totals = [
            'mosques' => Tenant::count(),
            'students' => Student::withoutGlobalScope('tenant')->count(),
            'teachers' => Teacher::withoutGlobalScope('tenant')->count(),
            'classrooms' => Classroom::withoutGlobalScope('tenant')->count(),
            'sections' => Section::withoutGlobalScope('tenant')->count(),
            'users' => User::withoutGlobalScope('tenant')->count(),
            'today_attendance' => AttendanceRecord::withoutGlobalScope('tenant')
                ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
                ->whereDate('attendance_sessions.date', today())
                ->count(),
        ];

        return view('super-admin.dashboard', [
            'mosques' => $mosques,
            'totals' => $totals,
        ]);
    }
}
