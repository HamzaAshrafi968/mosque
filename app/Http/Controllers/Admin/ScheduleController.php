<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\Schedule\GenerateWeeklySchedulesAction;
use App\Actions\Admin\Schedule\ResolveScheduleProgramAction;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\StudySession;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\ProgramService;
use App\Support\ScheduleRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
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
                'studySession:id,name',
            ])
            ->when($request->filled('classroom_id'), fn ($q) => $q->where('classroom_id', $request->input('classroom_id')))
            ->when($request->filled('teacher_id'), fn ($q) => $q->where('teacher_id', $request->input('teacher_id')))
            ->when($request->filled('program_id'), fn ($q) => $q->where('program_id', $request->input('program_id')))
            ->when($request->filled('study_session_id'), fn ($q) => $q->where('study_session_id', $request->input('study_session_id')))
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get();

        return view('admin.schedules.index', [
            'schedules' => $schedules,
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
            'studySessions' => StudySession::orderBy('name')->get(['id', 'name']),
            'currentSessionId' => config('app.current_study_session_id'),
        ]);
    }

    public function store(Request $request, ResolveScheduleProgramAction $resolveProgram): RedirectResponse
    {
        $data = $request->validate(ScheduleRules::rules());

        Schedule::create($resolveProgram->execute($data));

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
}
