<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Contracts\Repositories\TeacherRepositoryInterface;
use App\Models\TeacherWorkHour;
use App\Models\WorkSlot;
use App\Services\PayrollPeriodService;
use App\Support\TimesheetPayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API بوابة الأستاذ: كشف فترات عمله الفعلية (عرض فقط).
 */
class TimesheetController extends BaseTeacherController
{
    public function __construct(
        TeacherRepositoryInterface $teacherRepository,
        private readonly PayrollPeriodService $payroll,
    ) {
        parent::__construct($teacherRepository);
    }

    public function index(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        $month = $this->resolveMonth($request);

        $slots = $this->payroll->slotsFor($teacher, $month);
        $summary = $this->payroll->summary($teacher, $month, null, $slots);

        return $this->success([
            'month' => $month->format('Y-m'),
            'planned_minutes' => (int) round(TeacherWorkHour::monthlyHours($teacher->id, $month) * 60),
            'summary' => TimesheetPayload::summary($summary),
            'slots_by_date' => $slots
                ->groupBy(fn (WorkSlot $slot) => $slot->date->toDateString())
                ->map(fn ($day) => $day->map(fn (WorkSlot $slot) => TimesheetPayload::slot($slot))->values()),
        ]);
    }

    private function resolveMonth(Request $request): CarbonImmutable
    {
        $input = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? null;

        return $input
            ? CarbonImmutable::createFromFormat('Y-m', $input)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();
    }
}
