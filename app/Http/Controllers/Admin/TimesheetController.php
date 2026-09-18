<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HourlyRate;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\TeacherWorkHour;
use App\Models\WorkSlot;
use App\Services\PayrollPeriodService;
use App\Services\WorkSlotService;
use App\Support\TimesheetAggregator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * مركز الكشوف: عرض هرمي يومي/أسبوعي/شهري لفترات العمل الفعلية مع التسجيل
 * والتعديل والتوليد من الجدول الأسبوعي.
 */
class TimesheetController extends Controller
{
    public function __construct(
        private readonly WorkSlotService $slots,
        private readonly PayrollPeriodService $payroll,
    ) {}

    public function index(Request $request): View
    {
        $view = $request->input('view', 'daily');

        if (! in_array($view, ['daily', 'weekly', 'monthly'], true)) {
            $view = 'daily';
        }

        [$date, $dateInput] = $this->resolveDate($request);
        [$month, $monthInput] = $this->resolveMonth($request, $date);
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
            ->paginate(25)
            ->withQueryString();

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

        $aggregates = [];
        $payrollSummaries = [];

        foreach ($teachers as $teacher) {
            $rows = $slotsByTeacher->get($teacher->id, collect());

            $aggregates[$teacher->id] = [
                'total_minutes' => TimesheetAggregator::totalMinutes($rows),
                'by_date' => TimesheetAggregator::minutesByDate($rows),
                'by_week' => TimesheetAggregator::minutesByWeek($rows),
                'count' => $rows->count(),
            ];
        }

        if ($view === 'monthly') {
            $payrollSummaries = $this->payroll->summaries($teachers->getCollection(), $month);
        }

        return view('admin.timesheet.index', [
            'view' => $view,
            'teachers' => $teachers,
            'slotsByTeacher' => $slotsByTeacher,
            'aggregates' => $aggregates,
            'payrollSummaries' => $payrollSummaries,
            'date' => $date,
            'dateInput' => $dateInput,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekDays' => collect(range(0, 6))->map(fn (int $offset) => $weekStart->addDays($offset)),
            'month' => $month,
            'monthInput' => $monthInput,
            'monthWeeks' => TimesheetAggregator::monthWeeks($month->year, $month->month),
            'search' => $search,
            'sessionId' => $sessionId,
            'sessions' => StudySession::query()->orderBy('name')->get(),
        ]);
    }

    public function teacher(Teacher $teacher, Request $request): View
    {
        [$month, $monthInput] = $this->resolveMonth($request);
        $teacher->load(['studySession:id,name', 'studySessions:id,name']);

        $slots = $this->payroll->slotsFor($teacher, $month);
        $summary = $this->payroll->summary($teacher, $month, null, $slots);

        return view('admin.timesheet.teacher', [
            'teacher' => $teacher,
            'month' => $month,
            'monthInput' => $monthInput,
            'weeks' => TimesheetAggregator::monthWeeks($month->year, $month->month),
            'slotsByDate' => $slots->groupBy(fn (WorkSlot $slot) => $slot->date->toDateString()),
            'summary' => $summary,
            'weeklyTotal' => TeacherWorkHour::weeklyTotalHours($teacher->id),
            'rates' => HourlyRate::query()
                ->where('teacher_id', $teacher->id)
                ->orderByDesc('effective_from')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $teacher = Teacher::query()->findOrFail($data['teacher_id']);

        try {
            $this->slots->create($teacher, $data, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'تمت إضافة فترة العمل');
    }

    public function update(Request $request, WorkSlot $workSlot): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->slots->update($workSlot, $data, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'تم تحديث فترة العمل');
    }

    public function destroy(Request $request, WorkSlot $workSlot): RedirectResponse
    {
        try {
            $this->slots->delete($workSlot, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'تم حذف فترة العمل');
    }

    public function generate(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        try {
            $created = $this->slots->generateFromSchedule($teacher, $data['date'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with(
            'success',
            $created > 0
                ? "تم توليد {$created} فترة من الجدول الأسبوعي"
                : 'لا فترات جديدة — لا جدول لهذا اليوم أو الفترات مسجلة مسبقاً'
        );
    }

    /** فترات يوم محدد (JSON) لفحص التداخل الفوري في الواجهة. */
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
            ->get(['id', 'start_time', 'end_time']);

        return response()->json([
            'slots' => $slots->map(fn (WorkSlot $slot) => [
                'id' => $slot->id,
                'start' => substr($slot->start_time, 0, 5),
                'end' => substr($slot->end_time, 0, 5),
            ])->values(),
        ]);
    }

    public function print(Teacher $teacher, Request $request): View
    {
        [$month, $monthInput] = $this->resolveMonth($request);
        $teacher->load(['studySession:id,name', 'studySessions:id,name']);

        $slots = $this->payroll->slotsFor($teacher, $month);

        return view('admin.timesheet.print', [
            'teacher' => $teacher,
            'month' => $month,
            'monthInput' => $monthInput,
            'weeks' => TimesheetAggregator::monthWeeks($month->year, $month->month),
            'slotsByDate' => $slots->groupBy(fn (WorkSlot $slot) => $slot->date->toDateString()),
            'summary' => $this->payroll->summary($teacher, $month, null, $slots),
        ]);
    }

    /** @return array{0: CarbonImmutable, 1: string} */
    private function resolveDate(Request $request): array
    {
        $input = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? null;
        $date = $input
            ? CarbonImmutable::createFromFormat('Y-m-d', $input)->startOfDay()
            : CarbonImmutable::now()->startOfDay();

        return [$date, $date->toDateString()];
    }

    /** @return array{0: CarbonImmutable, 1: string} */
    private function resolveMonth(Request $request, ?CarbonImmutable $fallback = null): array
    {
        $input = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? null;
        $month = $input
            ? CarbonImmutable::createFromFormat('Y-m', $input)->startOfMonth()
            : ($fallback ?? CarbonImmutable::now())->startOfMonth();

        return [$month, $month->format('Y-m')];
    }
}
