<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Schedule\SaveScheduleSlotsAction;
use App\Enums\ScheduleDuration;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\ProgramService;
use App\Services\SessionService;
use App\Support\ScheduleRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly SessionService $sessions,
    ) {}

    public function index(Request $request, ProgramService $programService): View
    {
        // فلتر الدوام في الصفحة يتقدّم على دوام الترويسة عند إرساله صراحةً
        // (حتى لا يتعارضا فيظهر الجدول فارغاً).
        $effectiveSessionId = $request->has('study_session_id')
            ? ($request->input('study_session_id') ?: null)
            : config('app.current_study_session_id');

        $schedules = Schedule::query()
            ->withoutGlobalScope('study_session')
            ->notExpired()
            ->with([
                'classroom:id,name',
                'section:id,name',
                'subject:id,name',
                'teacher:id,name',
                'program:id,name,color',
                'programPeriod:id,name,starts_at,ends_at',
                'studySession:id,name,gender',
            ])
            ->when($effectiveSessionId, fn ($q) => $q->where('study_session_id', $effectiveSessionId))
            ->when($request->filled('classroom_id'), fn ($q) => $q->where('classroom_id', $request->input('classroom_id')))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->input('teacher_id')))
            ->when($request->filled('program_id'), fn ($q) => $q->where('program_id', $request->input('program_id')))
            ->orderByStudySession()
            ->get();

        // الصفوف والشعب تتبع الدوام المعروض: دوام الصف أو الصفوف المشتركة.
        $classrooms = Classroom::query()
            ->withoutGlobalScope('study_session')
            ->when($effectiveSessionId, fn ($q) => $q->where(
                fn ($sub) => $sub->where('study_session_id', $effectiveSessionId)->orWhereNull('study_session_id')
            ))
            ->with(['sections' => fn ($q) => $q->withoutGlobalScope('study_session')
                ->select('id', 'classroom_id', 'name', 'study_session_id')
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        $teachers = Teacher::query()
            ->withoutGlobalScope('study_session')
            ->where('is_active', true)
            ->when($effectiveSessionId, fn ($q) => $q->where(function ($sub) use ($effectiveSessionId) {
                $sub->where('study_session_id', $effectiveSessionId)
                    ->orWhereHas('studySessions', fn ($relation) => $relation->whereKey($effectiveSessionId));
            }))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.schedules.index', [
            'schedules' => $schedules,
            'exceptions' => $this->sessions->upcomingExceptions($schedules->pluck('id')),
            'classrooms' => $classrooms,
            'subjects' => Subject::orderBy('name')->get(['id', 'name']),
            'teachers' => $teachers,
            'programs' => Program::query()
                ->active()
                ->with(['periods' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'sessionProgramMap' => $programService->sessionProgramMap(),
            'classroomSessionMap' => $classrooms
                ->filter(fn (Classroom $classroom) => $classroom->study_session_id !== null)
                ->mapWithKeys(fn (Classroom $classroom) => [$classroom->id => $classroom->study_session_id]),
            'sectionSessionMap' => $classrooms
                ->flatMap(fn (Classroom $classroom) => $classroom->sections)
                ->filter(fn ($section) => $section->study_session_id !== null)
                ->mapWithKeys(fn ($section) => [$section->id => $section->study_session_id]),
            'studySessions' => StudySession::orderForDisplay()->get(['id', 'name', 'gender']),
            'currentSessionId' => $effectiveSessionId,
            'durations' => ScheduleDuration::cases(),
        ]);
    }

    /**
     * النموذج الموحّد: أيام الأسبوع + مدة الصلاحية (يوم/أسبوع/شهر/حتى انتهاء
     * الدورة/مفتوحة) — تُنشأ حصة لكل يوم بنفس النطاق الزمني.
     */
    public function store(Request $request, SaveScheduleSlotsAction $save): RedirectResponse
    {
        $data = $request->validate(ScheduleRules::rules());

        $result = $save->execute($data);

        return back()->with('success', $this->addedMessage($result['created'], $result['skipped']));
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

    /** رسالة نتيجة الإضافة مع تخطي الصفوف المطابقة الموجودة مسبقاً. */
    private function addedMessage(int $created, int $skipped): string
    {
        if ($created === 0 && $skipped > 0) {
            return 'الحصة موجودة مسبقاً — لم تُضف حصة جديدة';
        }

        $message = 'تمت إضافة '.$created.' '.($created === 1 ? 'حصة' : 'حصص');

        if ($skipped > 0) {
            $message .= "، وتخطي {$skipped} موجودة مسبقاً";
        }

        return $message;
    }
}
