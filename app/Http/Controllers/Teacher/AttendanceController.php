<?php

namespace App\Http\Controllers\Teacher;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AttendanceController extends BaseTeacherController
{
    public function create(Request $request): View
    {
        $classroomId = $request->input('classroom_id');
        $sectionId = $request->input('section_id');
        $date = $request->input('date', now()->toDateString());

        $students = collect();
        $existing = collect();

        if ($classroomId) {
            $students = Student::query()
                ->active()
                ->where('classroom_id', $classroomId)
                ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
                ->orderBy('name')
                ->get(['id', 'name', 'gender']);

            $existing = Attendance::query()
                ->whereDate('date', $date)
                ->whereIn('student_id', $students->pluck('id'))
                ->pluck('status', 'student_id');
        }

        return view('teacher.attendance.create', [
            'classrooms' => Classroom::with('sections:id,classroom_id,name')->orderBy('name')->get(),
            'students' => $students,
            'existing' => $existing,
            'classroomId' => $classroomId,
            'sectionId' => $sectionId,
            'date' => $date,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['in:present,absent,late'],
        ]);

        $tenantId = $request->user()->tenant_id;

        $validIds = Student::query()
            ->active()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', array_keys($data['statuses']))
            ->pluck('id')
            ->all();

        $invalidIds = array_diff(array_keys($data['statuses']), $validIds);

        if ($invalidIds !== []) {
            return back()
                ->withErrors(['statuses' => 'يحتوي الطلب على طلاب غير مسجلين أو محذوفين في هذا الجامع'])
                ->withInput();
        }

        $now = now();

        $rows = collect($data['statuses'])->map(fn ($status, $studentId) => [
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'student_id' => $studentId,
            'teacher_id' => null,
            'recorded_by' => $request->user()->id,
            'date' => $data['date'],
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all();

        DB::transaction(function () use ($rows) {
            Attendance::upsert(
                $rows,
                ['tenant_id', 'student_id', 'date'],
                ['status', 'recorded_by', 'updated_at']
            );
        });

        DashboardService::flush($tenantId);

        return back()->with('success', 'تم حفظ الحضور بنجاح');
    }
}
