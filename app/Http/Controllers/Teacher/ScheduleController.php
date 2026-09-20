<?php

namespace App\Http\Controllers\Teacher;

use App\Models\ClassSession;
use App\Models\Schedule;
use App\Services\ScheduleConflictService;
use App\Services\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends BaseTeacherController
{
    public function __construct(
        private readonly ScheduleConflictService $conflicts,
        private readonly SessionService $sessions,
    ) {}

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $schedules = Schedule::query()
            ->activeOn(now())
            ->with(['classroom:id,name', 'section:id,name', 'subject:id,name', 'program:id,name,color', 'programPeriod:id,name'])
            ->where('teacher_id', $teacher->id)
            ->orderByStudySession()
            ->get();

        return view('teacher.schedule', [
            'schedules' => $schedules->groupBy('day_of_week'),
            'conflicts' => $this->conflicts->findTeacherConflicts([$teacher->id]),
            'exceptions' => $this->sessions->upcomingExceptions($schedules->pluck('id')),
        ]);
    }

    /** إلغاء حصة في تاريخ محدد. */
    public function cancel(Request $request, Schedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->sessions->cancel($schedule, $data['date'], $data['reason'] ?? null, $request->user());

        return back()->with('success', 'تم إلغاء الحصة لهذا اليوم');
    }

    /** تأجيل حصة في تاريخ محدد إلى موعد جديد. */
    public function postpone(Request $request, Schedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'postponed_date' => ['required', 'date'],
            'postponed_starts_at' => ['required', 'date_format:H:i'],
            'postponed_ends_at' => ['required', 'date_format:H:i', 'after:postponed_starts_at'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->sessions->postpone(
            $schedule,
            $data['date'],
            $data['postponed_date'],
            $data['postponed_starts_at'],
            $data['postponed_ends_at'],
            $data['reason'] ?? null,
            $request->user()
        );

        return back()->with('success', 'تم تأجيل الحصة');
    }

    /** إرجاع الحصة لطبيعتها. */
    public function restore(Request $request, ClassSession $session): RedirectResponse
    {
        $this->sessions->restore($session, $request->user());

        return back()->with('success', 'تمت إعادة الحصة إلى موعدها');
    }
}
