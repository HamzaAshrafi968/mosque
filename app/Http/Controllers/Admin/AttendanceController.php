<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Teacher;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->input('date', now()->toDateString());
        $type = $request->input('type', 'students');

        $attendances = Attendance::query()
            ->with(['student:id,name,classroom_id', 'student.classroom:id,name', 'teacher:id,name'])
            ->whereDate('date', $date)
            ->when($type === 'students', fn ($q) => $q->whereNotNull('student_id'), fn ($q) => $q->whereNotNull('teacher_id'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->paginate(30)
            ->withQueryString();

        $teachers = Teacher::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.attendance.index', [
            'attendances' => $attendances,
            'teachers' => $teachers,
            'date' => $date,
            'type' => $type,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => [
                'required',
                'uuid',
                Rule::exists('teachers', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,late'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $now = now();

        Attendance::upsert(
            [[
                'id' => (string) Str::uuid(),
                'tenant_id' => $request->user()->tenant_id,
                'teacher_id' => $data['teacher_id'],
                'student_id' => null,
                'recorded_by' => $request->user()->id,
                'date' => $data['date'],
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['tenant_id', 'teacher_id', 'date'],
            ['status', 'notes', 'recorded_by', 'updated_at']
        );

        DashboardService::flush($request->user()->tenant_id);

        return back()->with('success', 'تم تسجيل حضور المعلم بنجاح');
    }
}
