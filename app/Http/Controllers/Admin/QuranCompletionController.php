<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuranCompletionStatus;
use App\Http\Controllers\Controller;
use App\Models\QuranCompletion;
use App\Models\Student;
use App\Services\AuditLogger;
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

    public function index(Request $request): View
    {
        $status = $request->input('status', 'pending');

        $completions = QuranCompletion::query()
            ->with(['student:id,name,classroom_id', 'student.classroom:id,name', 'confirmedBy:id,name'])
            ->when($status === 'pending', fn ($q) => $q->where('status', QuranCompletionStatus::Pending))
            ->when($status === 'confirmed', fn ($q) => $q->where('status', QuranCompletionStatus::Confirmed))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.quran.completions.index', [
            'completions' => $completions,
            'status' => $status,
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
            ->route('admin.quran.hafiz.index')
            ->with('success', 'تم تأكيد إتمام الحفظ: أصبح الطالب حافظاً والتحق بالبرنامج التأهيلي تلقائياً');
    }

    private function validated(Request $request): array
    {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $tenantId)],
            'completed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
