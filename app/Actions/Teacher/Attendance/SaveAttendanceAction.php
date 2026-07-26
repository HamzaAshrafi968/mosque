<?php

namespace App\Actions\Teacher\Attendance;

use App\Contracts\Repositories\AttendanceRepositoryInterface;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SaveAttendanceAction
{
    public function execute(AttendanceRepositoryInterface $repository, array $data, Request $request): void
    {
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

        $repository->upsertBatch($rows);

        DashboardService::flush($tenantId);
    }
}
