<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Teacher\Attendance\SaveAttendanceAction;
use App\Contracts\Repositories\AttendanceRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\AttendanceResource;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AttendanceController extends BaseApiController
{
    public function __construct(
        private readonly AttendanceRepositoryInterface $attendanceRepository,
    ) {}

    /**
     * Student attendance: attendance sessions of a date (or section).
     * Teacher attendance: daily rows of the legacy attendances table.
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->input('date', now()->toDateString());
        $type = $request->input('type', 'students');

        if ($type === 'teachers') {
            $rows = Attendance::query()
                ->whereDate('date', $date)
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->with(['student:id,name,classroom_id', 'student.classroom:id,name', 'teacher:id,name'])
                ->paginate(30);

            return $this->paginated(AttendanceResource::collection($rows));
        }

        $sessions = AttendanceSession::query()
            ->with(['section:id,name,classroom_id', 'section.classroom:id,name', 'records'])
            ->whereDate('date', $date)
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->input('section_id')))
            ->orderBy('date')
            ->paginate(30);

        return $this->paginated($sessions);
    }

    /** Record a full attendance session (or update the one for the section/date). */
    public function storeStudents(Request $request, SaveAttendanceAction $action): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'section_id' => ['nullable', 'uuid', 'exists:sections,id'],
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['in:present,absent,late,excused'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:500'],
        ]);

        $sessions = $action->execute($data, $request->user());

        return $this->success([
            'sessions' => $sessions->pluck('id'),
        ], 'تم حفظ الحضور بنجاح', 200);
    }

    public function storeTeachers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'teacher_id' => [
                'required',
                'uuid',
                Rule::exists('teachers', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,late,excused'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $now = now();

        $this->attendanceRepository->upsertTeacher([[
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
        ]]);

        DashboardService::flush($request->user()->tenant_id);

        return $this->success(message: 'تم تسجيل حضور المعلم بنجاح');
    }
}
