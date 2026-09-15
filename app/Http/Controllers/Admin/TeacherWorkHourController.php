<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WorkDay;
use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\TeacherWorkHour;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeacherWorkHourController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $search = $request->input('search');
        $day = $request->filled('day') ? (int) $request->input('day') : null;
        [$month, $monthInput] = $this->resolveMonth($request);

        $teachers = Teacher::query()
            ->with(['workHours' => fn ($query) => $query->orderBy('day_of_week')->orderBy('start_time')])
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($day !== null, fn ($query) => $query->whereHas('workHours', fn ($hours) => $hours->where('day_of_week', $day)))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $periods = TeacherWorkHour::query()
            ->whereIn('teacher_id', $teachers->pluck('id'))
            ->get()
            ->groupBy('teacher_id');

        return view('admin.work-hours.index', [
            'teachers' => $teachers,
            'days' => WorkDay::cases(),
            'search' => $search,
            'day' => $day,
            'month' => $month,
            'monthInput' => $monthInput,
            'totals' => $periods->map(fn ($rows) => round($rows->sum(fn (TeacherWorkHour $hour) => $hour->durationHours()), 2)),
            'monthlyTotals' => $periods->map(fn ($rows) => TeacherWorkHour::monthlyHoursFromPeriods($rows, $month)),
        ]);
    }

    public function teacherIndex(Teacher $teacher, Request $request): View
    {
        [$month, $monthInput] = $this->resolveMonth($request);

        $hours = $teacher->workHours()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        return view('admin.teachers.work-hours', [
            'teacher' => $teacher,
            'hours' => $hours,
            'days' => WorkDay::cases(),
            'weeklyTotal' => TeacherWorkHour::weeklyTotalHours($teacher->id),
            'monthlyTotal' => TeacherWorkHour::monthlyHours($teacher->id, $month),
            'month' => $month,
            'monthInput' => $monthInput,
        ]);
    }

    public function store(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $this->validated($request);
        $this->assertValidRange($teacher->id, $data);

        $workHour = TeacherWorkHour::create([
            'teacher_id' => $teacher->id,
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $this->audit->logModel('work_hours.created', $workHour, actor: $request->user());

        return redirect()
            ->route('admin.teachers.work-hours.index', $teacher)
            ->with('success', 'تمت إضافة فترة العمل');
    }

    public function update(Request $request, TeacherWorkHour $workHour): RedirectResponse
    {
        $data = $this->validated($request);
        $this->assertValidRange($workHour->teacher_id, $data, $workHour);

        $before = $workHour->getAttributes();

        $workHour->update([
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->logModel('work_hours.updated', $workHour, $before, actor: $request->user());

        return redirect()
            ->route('admin.teachers.work-hours.index', $workHour->teacher_id)
            ->with('success', 'تم تحديث فترة العمل');
    }

    public function destroy(Request $request, TeacherWorkHour $workHour): RedirectResponse
    {
        $teacherId = $workHour->teacher_id;

        $this->audit->logModel('work_hours.deleted', $workHour, actor: $request->user());
        $workHour->delete();

        return redirect()
            ->route('admin.teachers.work-hours.index', $teacherId)
            ->with('success', 'تم حذف فترة العمل');
    }

    /** الشهر المعروض (افتراضياً الشهر الحالي) لحساب الإجمالي الشهري. */
    private function resolveMonth(Request $request): array
    {
        $input = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? null;
        $month = $input
            ? CarbonImmutable::createFromFormat('Y-m', $input)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        return [$month, $month->format('Y-m')];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function assertValidRange(string $teacherId, array $data, ?TeacherWorkHour $ignore = null): void
    {
        $start = TeacherWorkHour::toMinutes($data['start_time']);
        $end = TeacherWorkHour::toMinutes($data['end_time']);

        if ($end <= $start) {
            throw ValidationException::withMessages([
                'end_time' => ['يجب أن يكون وقت النهاية بعد وقت البداية'],
            ]);
        }

        if ($end - $start > 12 * 60) {
            throw ValidationException::withMessages([
                'end_time' => ['لا يمكن أن تتجاوز الفترة الواحدة 12 ساعة'],
            ]);
        }

        $conflicts = TeacherWorkHour::query()
            ->where('teacher_id', $teacherId)
            ->where('day_of_week', $data['day_of_week'])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->get();

        foreach ($conflicts as $period) {
            if ($start < $period->endMinutes() && $end > $period->startMinutes()) {
                throw ValidationException::withMessages([
                    'start_time' => ["تتعارض مع فترة قائمة: {$period->start_time} — {$period->end_time}"],
                ]);
            }
        }
    }
}
