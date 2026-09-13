<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * إدارة الدوامات (الفترات الدراسية): كل دوام له طلابه وأساتذته وشعبه.
 * The manager switches the active session from the top header to filter the
 * whole admin panel (students / teachers / sections).
 */
class StudySessionController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $sessions = StudySession::query()
            ->with(['programs:id,name,color'])
            ->withCount([
                'students' => fn ($q) => $q->withoutGlobalScope('study_session'),
                'teachers' => fn ($q) => $q->withoutGlobalScope('study_session'),
                'sections' => fn ($q) => $q->withoutGlobalScope('study_session'),
            ])
            ->orderBy('name')
            ->get();

        return view('admin.sessions.index', [
            'sessions' => $sessions,
            'programs' => Program::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'color']),
            'currentSessionId' => config('app.current_study_session_id'),
            'unassigned' => [
                'students' => Student::query()->withoutGlobalScope('study_session')->whereNull('study_session_id')->count(),
                'teachers' => Teacher::query()->withoutGlobalScope('study_session')->whereNull('study_session_id')->count(),
                'sections' => Section::query()->withoutGlobalScope('study_session')->whereNull('study_session_id')->count(),
            ],
        ]);
    }

    /**
     * تخصيص البرامج/التخصصات المتاحة لكل دوام: مثال — الدوام الأول للتحفيظ
     * والإجازة، والثاني للتسميع فقط. بلا تحديد = كل البرامج متاحة.
     */
    public function syncPrograms(Request $request, StudySession $session): RedirectResponse
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        $data = $request->validate([
            'programs' => ['nullable', 'array'],
            'programs.*' => ['uuid', Rule::exists('programs', 'id')->where('tenant_id', $tenantId)],
        ]);

        $session->programs()->sync($data['programs'] ?? []);

        $this->audit->logModel('session.programs_updated', $session, actor: $request->user());

        return back()->with(
            'success',
            $session->programs()->exists()
                ? "تم تخصيص البرامج المتاحة لدوام \"{$session->name}\""
                : "تم مسح التخصيص — كل البرامج متاحة لدوام \"{$session->name}\""
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $session = StudySession::create($data);
        $this->audit->logModel('session.created', $session, actor: $request->user());

        return redirect()->route('admin.sessions.index')->with('success', "تمت إضافة \"{$session->name}\" بنجاح");
    }

    public function update(Request $request, StudySession $session): RedirectResponse
    {
        $before = $session->getAttributes();
        $session->update($this->validated($request, $session));
        $this->audit->logModel('session.updated', $session, $before, actor: $request->user());

        return back()->with('success', 'تم تحديث بيانات الدوام');
    }

    public function destroy(Request $request, StudySession $session): RedirectResponse
    {
        $unscoped = fn ($q) => $q->withoutGlobalScope('study_session');

        if ($session->students()->withoutGlobalScope('study_session')->exists()
            || $session->teachers()->withoutGlobalScope('study_session')->exists()
            || $session->sections()->withoutGlobalScope('study_session')->exists()) {
            return back()->withErrors(['session' => 'لا يمكن حذف دوام عليه طلاب أو أساتذة أو شعب — انقلهم إلى دوام آخر أولاً']);
        }

        $this->audit->logModel('session.deleted', $session, actor: $request->user());
        $session->delete();

        return redirect()->route('admin.sessions.index')->with('success', 'تم حذف الدوام');
    }

    /** Change the active دوام from the top header ('' = كل الدوامات). */
    public function switch(Request $request): RedirectResponse
    {
        $tenantId = config('app.current_tenant_id');

        $data = $request->validate([
            'study_session_id' => ['nullable', 'string', Rule::exists('study_sessions', 'id')->where('tenant_id', $tenantId)],
        ]);

        $sessionId = $data['study_session_id'] ?? null;

        if ($sessionId === null || $sessionId === '') {
            $request->session()->forget('study_session_id');

            return back()->with('success', 'أنت الآن تعرض: كل الدوامات');
        }

        $session = StudySession::find($sessionId);
        $request->session()->put('study_session_id', $sessionId);

        return back()->with('success', "أنت الآن تعرض دوام: {$session->name}");
    }

    /**
     * Quick-fix tool: move every unassigned record of one kind into a session.
     */
    public function assignUnassigned(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:students,teachers,sections'],
            'study_session_id' => ['required', Rule::exists('study_sessions', 'id')->where('tenant_id', config('app.current_tenant_id'))],
        ]);

        $session = StudySession::find($data['study_session_id']);

        $model = match ($data['type']) {
            'students' => Student::class,
            'teachers' => Teacher::class,
            default => Section::class,
        };

        DB::transaction(function () use ($model, $session) {
            $model::query()
                ->withoutGlobalScope('study_session')
                ->whereNull('study_session_id')
                ->update(['study_session_id' => $session->id]);
        });

        $label = match ($data['type']) {
            'students' => 'الطلاب',
            'teachers' => 'الأساتذة',
            default => 'الشعب',
        };

        $this->audit->log('session.bulk_assign', 'study_session', $session->id, $session->tenant_id, [
            'type' => $data['type'],
            'session_id' => $session->id,
        ], actor: $request->user());

        return back()->with('success', "تم توزيع كل {$label} غير المصنفين على دوام \"{$session->name}\"");
    }

    private function validated(Request $request, ?StudySession $session = null): array
    {
        $tenantId = config('app.current_tenant_id');

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('study_sessions', 'name')->where('tenant_id', $tenantId)->ignore($session),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);
    }
}
