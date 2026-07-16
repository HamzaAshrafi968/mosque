<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Models\Attendance;
use App\Models\Student;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttendanceController extends BaseTeacherController
{
    public function students(Request $request): JsonResponse
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

        return response()->json([
            'students' => $students,
            'existing' => $existing,
            'classroom_id' => $classroomId,
            'section_id' => $sectionId,
            'date' => $date,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'statuses' => ['required', 'array'],
            'statuses.*' => ['in:present,absent,late'],
        ]);

        $tenantId = $request->user()->tenant_id;
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

        return response()->json(['message' => 'تم حفظ الحضور بنجاح']);
    }
}
