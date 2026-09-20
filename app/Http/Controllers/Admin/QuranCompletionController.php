<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProgramType;
use App\Enums\QuranCompletionStatus;
use App\Http\Controllers\Controller;
use App\Models\HafizProfile;
use App\Models\ProgramEnrollment;
use App\Models\QuranCompletion;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\QuranProgramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuranCompletionController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly QuranProgramService $programs,
    ) {}

    /**
     * الصفحة الموحّدة: طلبات إتمام الحفظ (بانتظار التأكيد) + قائمة الحفاظ
     * المؤكدين (كانت صفحة «الحفاظ» المستقلة، دُمجت هنا).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $authorization = app(AuthorizationService::class);

        $status = $request->input('status');
        if (! in_array($status, ['pending', 'confirmed'], true)) {
            $status = $authorization->can($user, 'quran.completion.view') ? 'pending' : 'confirmed';
        }

        if ($status === 'pending' && ! $authorization->can($user, 'quran.completion.view')) {
            $status = 'confirmed';
        }

        $search = $request->input('q');

        $completions = null;
        $profiles = null;
        $qualifyingEnrollments = collect();

        if ($status === 'pending') {
            $completions = QuranCompletion::query()
                ->with(['student:id,name,classroom_id', 'student.classroom:id,name', 'confirmedBy:id,name'])
                ->where('status', QuranCompletionStatus::Pending)
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString();
        } else {
            $profiles = HafizProfile::query()
                ->with([
                    'student:id,name,classroom_id',
                    'student.classroom:id,name',
                    'student.latestConfirmedCompletion.confirmedBy:id,name',
                ])
                ->when($search, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('name', 'like', '%'.$search.'%')))
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString();

            // الترحيل للتأهيلي: يظهر الزر للحفاظ بلا التحاق سابق، وتظهر شارة
            // الحالة لمن التحق/أكمل — لتفادي إعادة ترحيل من أنهى التأهيلي.
            $qualifyingEnrollments = ProgramEnrollment::query()
                ->whereIn('student_id', $profiles->pluck('student_id'))
                ->where('program_type', ProgramType::Qualifying)
                ->get(['student_id', 'status'])
                ->keyBy('student_id');
        }

        return view('admin.quran.completions.index', [
            'status' => $status,
            'completions' => $completions,
            'profiles' => $profiles,
            'qualifyingEnrollments' => $qualifyingEnrollments,
            'search' => $search,
            'pendingCount' => QuranCompletion::query()->where('status', QuranCompletionStatus::Pending)->count(),
            'hafizCount' => HafizProfile::query()->count(),
            'can' => fn (string $permission) => $authorization->can($user, $permission),
        ]);
    }

    public function create(): View
    {
        return view('admin.quran.completions.create', [
            'students' => Student::query()
                ->active()
                ->whereDoesntHave('quranCompletions', fn ($q) => $q->where('status', QuranCompletionStatus::Confirmed))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $student = Student::findOrFail($data['student_id']);

        $completion = $this->programs->recordCompletion(
            $student,
            $data['completed_at'],
            $data['notes'] ?? null,
            $request->user()
        );

        return redirect()
            ->route('admin.quran.completions.index', ['status' => 'pending'])
            ->with('success', 'تم تسجيل إتمام الحفظ — بانتظار التأكيد ليصبح الطالب حافظاً ويدخل البرنامج التأهيلي تلقائياً');
    }

    /** تأكيد الإتمام → حافظ + التحاق تلقائي بالبرنامج التأهيلي + فتح الاختبارات الشهرية. */
    public function confirm(Request $request, QuranCompletion $completion): RedirectResponse
    {
        try {
            $this->programs->confirmCompletion($completion, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('admin.quran.completions.index', ['status' => 'confirmed'])
            ->with('success', 'تم تأكيد إتمام الحفظ: أصبح الطالب حافظاً والتحق بالبرنامج التأهيلي تلقائياً');
    }

    private function validated(Request $request): array
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        return $request->validate([
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'completed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
