<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Schedule\GenerateWeeklySchedulesAction;
use App\Actions\Admin\Schedule\ResolveScheduleProgramAction;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\ProgramService;
use App\Services\ScheduleConflictService;
use App\Services\SessionService;
use App\Support\ScheduleRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleConflictService $conflicts,
        private readonly SessionService $sessions,
    ) {}

    public function index(Request $request, ProgramService $programService): View
    {
        $schedules = Schedule::query()
            ->with([
                'classroom:id,name',
                'section:id,name',
                'subject:id,name',
                'teacher:id,name',
                'program:id,name,color',
                'programPeriod:id,name,starts_at,ends_at',
                'studySession:id,name,gender',
            ])
            ->when($request->filled('classroom_id'), fn ($q) => $q->where('classroom_id', $request->input('classroom_id')))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->input('teacher_id')))
            ->when($request->filled('program_id'), fn ($q) => $q->where('program_id', $request->input('program_id')))
            ->when($request->filled('study_session_id'), fn ($q) => $q->where('study_session_id', $request->input('study_session_id')))
            ->orderByStudySession()
            ->get();

        // The weekly grid needs the full collection; the flat table below it is
        // paginated in-memory (no extra queries, bounded rendering).
        $perPage = 50;
        $page = max(1, (int) $request->input('page', 1));

        $table = new LengthAwarePaginator(
            $schedules->forPage($page, $perPage)->values(),
            $schedules->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.schedules.index', [
            'schedules' => $schedules,
            'table' => $table,
            'exceptions' => $this->sessions->upcomingExceptions($schedules->pluck('id')),
            'classrooms' => Classroom::with('sections:id,classroom_id,name')->orderBy('name')->get(),
            'subjects' => Subject::orderBy('name')->get(['id', 'name']),
            'teachers' => Teacher::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'programs' => Program::query()
                ->active()
                ->with(['periods' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'sessionProgramMap' => $programService->sessionProgramMap(),
            'studySessions' => StudySession::orderForDisplay()->get(['id', 'name', 'gender']),
            'currentSessionId' => config('app.current_study_session_id'),
        ]);
    }

    public function store(Request $request, ResolveScheduleProgramAction $resolveProgram): RedirectResponse
    {
        $data = $request->validate(ScheduleRules::rules());

        $slot = $resolveProgram->execute($data);

        $this->conflicts->withScheduleLock($this->lockKeys($slot), function () use ($slot) {
            DB::transaction(function () use ($slot) {
                $this->conflicts->assertSlot($slot);
                Schedule::create($slot);
            });
        });

        return back()->with('success', 'تمت إضافة الحصة');
    }

    /**
     * يولّد جدولاً أسبوعياً لبرنامج/فترة (تخصص) عبر عدة أيام — مثال:
     * برنامج التحفيظ الفترة الأولى فقط، أو برنامج الإجازة، اختبارات الحفظ،
     * الدورات الشرعية، البرامج القرآنية.
     */
    public function generate(Request $request, GenerateWeeklySchedulesAction $generate): RedirectResponse
    {
        $data = $request->validate(ScheduleRules::rules(weekly: true));

        $result = $generate->execute($data);

        $message = "تم توليد {$result['created']} حصة أسبوعية";

        if ($result['skipped'] > 0) {
            $message .= "، وتخطي {$result['skipped']} حصة موجودة مسبقاً";
        }

        return back()->with('success', $message);
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return back()->with('success', 'تم حذف الحصة');
    }

    /** إلغاء حصة في تاريخ محدد (يوم واحد فقط). */
    public function cancel(Request $request, Schedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->sessions->cancel($schedule, $data['date'], $data['reason'] ?? null, $request->user());

        return back()->with('success', 'تم إلغاء الحصة لهذا اليوم');
    }

    /** تأجيل حصة في تاريخ محدد إلى موعد جديد بعد فحص التعارضات. */
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

    /** إرجاع الحصة لطبيعتها بحذف الاستثناء. */
    public function restore(Request $request, ClassSession $session): RedirectResponse
    {
        $this->sessions->restore($session, $request->user());

        return back()->with('success', 'تمت إعادة الحصة إلى موعدها');
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return array<int, string>
     */
    private function lockKeys(array $slot): array
    {
        return array_filter([
            ! empty($slot['teacher_id']) ? 'teacher:'.$slot['teacher_id'] : null,
            ! empty($slot['section_id']) ? 'section:'.$slot['section_id'] : null,
            ! empty($slot['classroom_id']) ? 'classroom:'.$slot['classroom_id'] : null,
        ]);
    }
}
