<?php

namespace App\Http\Controllers\Teacher;

use App\Models\WorkSlot;
use App\Services\PayrollPeriodService;
use App\Support\TimesheetAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * بوابة الأستاذ: كشف فترات عمله الفعلية (شهر → أسبوع → يوم) للعرض فقط،
 * فالتسجيل من صلاحيات مدير الجامع.
 */
class TimesheetController extends BaseTeacherController
{
    public function __construct(private readonly PayrollPeriodService $payroll) {}

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $input = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? null;
        $month = $input
            ? CarbonImmutable::createFromFormat('Y-m', $input)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        $slots = $this->payroll->slotsFor($teacher, $month);

        return view('teacher.timesheet.index', [
            'teacher' => $teacher,
            'month' => $month,
            'monthInput' => $month->format('Y-m'),
            'weeks' => TimesheetAggregator::monthWeeks($month->year, $month->month),
            'slotsByDate' => $slots->groupBy(fn (WorkSlot $slot) => $slot->date->toDateString()),
            'summary' => $this->payroll->summary($teacher, $month, null, $slots),
        ]);
    }
}
