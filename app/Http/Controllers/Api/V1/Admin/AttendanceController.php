<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\AttendanceRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\AttendanceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
