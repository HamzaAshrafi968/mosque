<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\AttendanceRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\AttendanceResource;
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

    public function index(Request $request): JsonResponse
    {
        $attendances = $this->attendanceRepository->paginateWithFilters([
            'date' => $request->input('date', now()->toDateString()),
            'type' => $request->input('type', 'students'),
            'status' => $request->input('status'),
        ]);

        return $this->paginated(AttendanceResource::collection($attendances));
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
            'status' => ['required', 'in:present,absent,late'],
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
