<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
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
            ->get()
            ->map(function (Tenant $mosque) {
                $mosque->setAttribute('students_count', Student::withoutGlobalScope('tenant')
                    ->where('students.tenant_id', $mosque->id)->count());

                $mosque->setAttribute('teachers_count', Teacher::withoutGlobalScope('tenant')
                    ->where('teachers.tenant_id', $mosque->id)->count());

                $mosque->setAttribute('classrooms_count', Classroom::withoutGlobalScope('tenant')
                    ->where('classrooms.tenant_id', $mosque->id)->count());

                $mosque->setAttribute('pending_approvals', Grade::withoutGlobalScope('tenant')
                    ->where('grades.tenant_id', $mosque->id)
                    ->where('grades.status', 'submitted')
                    ->count());

                return $mosque;
            });

        $totals = [
            'mosques' => Tenant::count(),
            'students' => Student::withoutGlobalScope('tenant')->count(),
            'teachers' => Teacher::withoutGlobalScope('tenant')->count(),
            'classrooms' => Classroom::withoutGlobalScope('tenant')->count(),
            'sections' => Section::withoutGlobalScope('tenant')->count(),
            'users' => User::withoutGlobalScope('tenant')->count(),
            'today_attendance' => Attendance::withoutGlobalScope('tenant')
                ->whereDate('attendances.date', today())
                ->whereNotNull('student_id')
                ->count(),
        ];

        return view('super-admin.dashboard', [
            'mosques' => $mosques,
            'totals' => $totals,
        ]);
    }
}
