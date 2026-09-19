<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\TeacherWorkHour;
use App\Models\WorkSlot;
use App\Services\PayrollPeriodService;
use App\Services\WorkSlotService;
use App\Support\TimesheetAggregator;
use App\Support\TimesheetPayload;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * API كشوف العمل الفعلية (admin): يومي/أسبوعي/شهري، تسجيل الفترات،
 * والتوليد من الجدول الأسبوعي — بنفس قواعد الويب.
 */
class TimesheetController extends BaseApiController
{
    public function __construct(
        private readonly WorkSlotService $slots,
        private readonly PayrollPeriodService $payroll,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $view = $request->input('view', 'daily');

        if (! in_array($view, ['daily', 'weekly', 'monthly'], true)) {
            $view = 'daily';
        }

        $date = $this->resolveDate($request);
        $month = $this->resolveMonth($request, $date);
        $search = $request->string('q')->toString();
        $sessionId = $request->input('session');

        $weekStart = TimesheetAggregator::weekStart($date);
        $weekEnd = $weekStart->endOfWeek(Carbon::SATURDAY);

        $teachers = Teacher::query()
            ->with(['studySession:id,name', 'studySessions:id,name'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($sessionId, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('study_session_id', $sessionId)
                ->orWhereHas('studySessions', fn ($sessions) => $sessions->whereKey($sessionId))))
            ->orderBy('name')
            ->paginate(25);

        [$rangeStart, $rangeEnd] = match ($view) {
            'weekly' => [$weekStart, $weekEnd],
            'monthly' => [$month->startOfMonth(), $month->endOfMonth()],
            default => [$date, $date],
        };

        $slotsByTeacher = WorkSlot::query()
            ->whereIn('teacher_id', $teachers->pluck('id'))
            ->between($rangeStart, $rangeEnd)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->groupBy('teacher_id');

        $payrollSummaries = $view === 'monthly'
            ? $this->payroll->summaries($teachers->getCollection(), $month)
            : [];

        $rows = $teachers->getCollection()->map(function (Teacher $teacher) use ($slotsByTeacher, $payrollSummaries) {
            $slots = $slotsByTeacher->get($teacher->id, collect());

            return [
                'teacher' => TimesheetPayload::teacher($teacher),
                'total_minutes' => TimesheetAggregator::totalMinutes($slots),
                'total_label' => WorkSlot::formatMinutes(TimesheetAggregator::totalMinutes($slots)),
                'by_date' => TimesheetAggregator::minutesByDate($slots),
                'by_week' => TimesheetAggregator::minutesByWeek($slots),
                'slots' => $slots->map(fn (WorkSlot $slot) => TimesheetPayload::slot($slot))->values(),
                'payroll' => isset($payrollSummaries[$teacher->id])
                    ? TimesheetPayload::summary($payrollSummaries[$teacher->id])
                    : null,
            ];
        });

        return $this->success([
            'view' => $view,
            'date' => $date->toDateString(),
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'month' => $month->format('Y-m'),
            'month_weeks' => collect(TimesheetAggregator::monthWeeks($month->year, $month->month))
                ->map(fn (array $week) => [
                    'start' => $week['start']->toDateString(),
                    'end' => $week['end']->toDateString(),
                    'label' => $week['label'],
                    'starts_before_month' => $week['starts_before_month'],
                    'ends_after_month' => $week['ends_after_month'],
                ])->values(),
            'sessions' => StudySession::query()->orderBy('name')->get(['id', 'name']),
            'teachers' => $rows->values(),
            'pagination' => [
                'current_page' => $teachers->currentPage(),
                'last_page' => $teachers->lastPage(),
                'per_page' => $teachers->perPage(),
                'total' => $teachers->total(),
            ],
        ]);
    }

    public function teacher(Teacher $teacher, Request $request): JsonResponse
    {
        $month = $this->resolveMonth($request);
        $teacher->load(['studySession:id,name', 'studySessions:id,name']);

        $slots = $this->payroll->slotsFor($teacher, $month);
        $summary = $this->payroll->summary($teacher, $month, null, $slots);

        return $this->success([
            'teacher' => TimesheetPayload::teacher($teacher),
            'month' => $month->format('Y-m'),
            'planned_minutes' => (int) round(TeacherWorkHour::monthlyHours($teacher->id, $month) * 60),
            'summary' => TimesheetPayload::summary($summary),
            'slots_by_date' => $slots
                ->groupBy(fn (WorkSlot $slot) => $slot->date->toDateString())
                ->map(fn ($day) => $day->map(fn (WorkSlot $slot) => TimesheetPayload::slot($slot))->values()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'required_without:hours', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_without:hours', 'date_format:H:i'],
            'hours' => ['nullable', 'required_without:start_time', 'numeric', 'min:0.25', 'max:'.$this->slots->maxSlotHours()],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $teacher = Teacher::query()->find($data['teacher_id']);

        if ($teacher === null) {
            return $this->notFound('المعلم غير موجود');
        }

        try {
            $slot = $this->slots->create($teacher, $data, $request->user());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->created(TimesheetPayload::slot($slot), 'تمت إضافة فترة العمل');
    }

    public function update(Request $request, WorkSlot $workSlot): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'required_without:hours', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_without:hours', 'date_format:H:i'],
            'hours' => ['nullable', 'required_without:start_time', 'numeric', 'min:0.25', 'max:'.$this->slots->maxSlotHours()],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->slots->update($workSlot, $data, $request->user());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->success(TimesheetPayload::slot($workSlot->fresh()), 'تم تحديث فترة العمل');
    }

    public function destroy(Request $request, WorkSlot $workSlot): JsonResponse
    {
        try {
            $this->slots->delete($workSlot, $request->user());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->noContent();
    }

    public function generate(Request $request, Teacher $teacher): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        try {
            $created = $this->slots->generateFromSchedule($teacher, $data['date'], $request->user());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        }

        return $this->created(
            ['created' => $created],
            $created > 0 ? "تم توليد {$created} فترة من الجدول الأسبوعي" : 'لا فترات جديدة'
        );
    }

    public function daySlots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $slots = WorkSlot::query()
            ->forTeacher($data['teacher_id'])
            ->onDate($data['date'])
            ->orderBy('start_time')
            ->get();

        return $this->success([
            'slots' => $slots->map(fn (WorkSlot $slot) => TimesheetPayload::slot($slot))->values(),
        ]);
    }

    private function resolveDate(Request $request): CarbonImmutable
    {
        $input = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? null;

        return $input
            ? CarbonImmutable::createFromFormat('Y-m-d', $input)->startOfDay()
            : CarbonImmutable::now()->startOfDay();
    }

    private function resolveMonth(Request $request, ?CarbonImmutable $fallback = null): CarbonImmutable
    {
        $input = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? null;

        return $input
            ? CarbonImmutable::createFromFormat('Y-m', $input)->startOfMonth()
            : ($fallback ?? CarbonImmutable::now())->startOfMonth();
    }
}
