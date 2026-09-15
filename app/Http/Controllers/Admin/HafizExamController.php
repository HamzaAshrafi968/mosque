<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HafizExamRevision;
use App\Models\HafizMonthlyExam;
use App\Models\HafizProfile;
use App\Models\QuranSurah;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\QuranProgramService;
use App\Support\QuranProgramSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HafizExamController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly QuranProgramService $programs,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($this->isValidMonth($request->input('month'))) {
            return redirect()->route('admin.quran.exams.month', $request->input('month'));
        }

        $year = $this->validYear($request->input('year'));

        $hafizIds = HafizProfile::query()->pluck('student_id');

        return view('admin.quran.exams.year', [
            'year' => $year,
            'months' => $this->programs->examYearSummaries($hafizIds, $year),
            'hafizCount' => $hafizIds->count(),
        ]);
    }

    public function month(Request $request, string $month): View
    {
        if (! $this->isValidMonth($month)) {
            abort(404);
        }

        $hafizIds = HafizProfile::query()->pluck('student_id');

        $exams = $this->programs->ensureMonthlyExamRows($hafizIds, $month, $request->user());

        return view('admin.quran.exams.month', [
            'exams' => $exams,
            'month' => $month,
            'previousMonth' => QuranProgramSettings::previousMonth($month),
            'nextMonth' => QuranProgramSettings::nextMonth($month),
            'monthLabel' => fn (string $m) => QuranProgramSettings::monthLabel($m),
        ]);
    }

    public function show(HafizMonthlyExam $exam): View
    {
        $exam->load(['student:id,name,classroom_id', 'student.classroom:id,name', 'revisions.fromSurah:id,name_arabic', 'revisions.toSurah:id,name_arabic']);

        return view('admin.quran.exams.show', [
            'exam' => $exam,
            'supervisors' => Teacher::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'surahs' => QuranSurah::orderBy('sort_order')->get(['id', 'name_arabic']),
            'passMark' => QuranProgramSettings::HAFIZ_EXAM_PASS_MARK,
            'monthLabel' => fn (string $m) => QuranProgramSettings::monthLabel($m),
        ]);
    }

    /** تسجيل/تعديل نتيجة الاختبار الشهري (الدرجة تحدد النتيجة وفق القاعدة). */
    public function grade(Request $request, HafizMonthlyExam $exam): RedirectResponse
    {
        $data = $this->gradingValidated($request);

        $this->programs->gradeMonthlyExam($exam, $data, $request->user());

        $this->storeRevisions($exam, $request, $request->user());

        $label = (float) $data['grade'] >= QuranProgramSettings::HAFIZ_EXAM_PASS_MARK ? 'نجاح' : 'إعادة';

        return redirect()
            ->route('admin.quran.exams.show', $exam)
            ->with('success', 'تم تسجيل نتيجة الشهر ('.$label.') مع حفظ تاريخ التقييمات السابقة');
    }

    /** إضافة جزء/مقدار مطلوب إعادته لاختبار راسب. */
    public function storeRevision(Request $request, HafizMonthlyExam $exam): RedirectResponse
    {
        $data = $this->revisionValidated($request);

        if (! $data['juz'] && ! $data['from_surah'] && ! $data['to_surah'] && ! $data['amount']) {
            throw ValidationException::withMessages([
                'juz' => ['حدد الجزء أو السورة/المقدار المطلوب إعادته'],
            ]);
        }

        $revision = HafizExamRevision::create([
            'exam_id' => $exam->id,
            'juz' => $data['juz'] ?? null,
            'from_surah' => $data['from_surah'] ?? null,
            'from_ayah' => $data['from_ayah'] ?? null,
            'to_surah' => $data['to_surah'] ?? null,
            'to_ayah' => $data['to_ayah'] ?? null,
            'amount' => $data['amount'] ?? null,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->logModel('hafiz_exam.revision_recorded', $revision, actor: $request->user());

        return back()->with('success', 'تم تسجيل الجزء المطلوب إعادته');
    }

    /** إكمال إعادة التلاوة (المشرف/المعلم). */
    public function completeRevision(Request $request, HafizExamRevision $revision): RedirectResponse
    {
        if ($revision->status?->value !== 'pending') {
            abort(422, 'لا يمكن إكمال هذا الطلب');
        }

        $before = $revision->getAttributes();
        $revision->update(['status' => 'completed']);
        $this->audit->logModel('hafiz_exam.revision_completed', $revision, $before, actor: $request->user());

        return back()->with('success', 'تم تأكيد إكمال إعادة الجزء');
    }

    /** اعتماد إعادة التلاوة (الإدارة). */
    public function approveRevision(Request $request, HafizExamRevision $revision): RedirectResponse
    {
        if ($revision->status?->value !== 'completed') {
            abort(422, 'لا يمكن اعتماد مراجعة غير مكتملة');
        }

        $before = $revision->getAttributes();
        $revision->update(['status' => 'approved']);
        $this->audit->logModel('hafiz_exam.revision_approved', $revision, $before, actor: $request->user());

        return back()->with('success', 'تم اعتماد إعادة الجزء');
    }

    private function storeRevisions(HafizMonthlyExam $exam, Request $request, $actor): void
    {
        $payload = $request->input('revisions', []);

        foreach ($payload as $row) {
            $row = array_filter($row, fn ($v) => $v !== null && $v !== '');

            if (! isset($row['juz'], $row['from_surah'], $row['to_surah'], $row['amount'])
                && $row === []) {
                continue;
            }

            if (! ($row['juz'] ?? null) && ! ($row['from_surah'] ?? null) && ! ($row['to_surah'] ?? null) && ! ($row['amount'] ?? null)) {
                continue;
            }

            $revision = HafizExamRevision::create([
                'exam_id' => $exam->id,
                'juz' => $row['juz'] ?? null,
                'from_surah' => $row['from_surah'] ?? null,
                'from_ayah' => $row['from_ayah'] ?? null,
                'to_surah' => $row['to_surah'] ?? null,
                'to_ayah' => $row['to_ayah'] ?? null,
                'amount' => $row['amount'] ?? null,
                'status' => 'pending',
                'notes' => $row['notes'] ?? null,
            ]);

            $this->audit->logModel('hafiz_exam.revision_recorded', $revision, actor: $actor);
        }
    }

    private function gradingValidated(Request $request): array
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        return $request->validate([
            'grade' => ['required', 'numeric', 'min:0', 'max:100'],
            'supervisor_id' => ['nullable', 'uuid', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)],
            'exam_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'revisions' => ['nullable', 'array'],
            'revisions.*.juz' => ['nullable', 'integer', 'between:1,30'],
            'revisions.*.from_surah' => ['nullable', 'uuid', 'exists:quran_surahs,id'],
            'revisions.*.from_ayah' => ['nullable', 'integer', 'min:1'],
            'revisions.*.to_surah' => ['nullable', 'uuid', 'exists:quran_surahs,id'],
            'revisions.*.to_ayah' => ['nullable', 'integer', 'min:1'],
            'revisions.*.amount' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'revisions.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function revisionValidated(Request $request): array
    {
        return $request->validate([
            'juz' => ['nullable', 'integer', 'between:1,30'],
            'from_surah' => ['nullable', 'uuid', 'exists:quran_surahs,id'],
            'from_ayah' => ['nullable', 'integer', 'min:1'],
            'to_surah' => ['nullable', 'uuid', 'exists:quran_surahs,id'],
            'to_ayah' => ['nullable', 'integer', 'min:1'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function isValidMonth(?string $month): bool
    {
        return is_string($month) && (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month);
    }

    private function validYear(mixed $year): int
    {
        $current = (int) now()->format('Y');

        if (is_numeric($year) && (int) $year >= 2000 && (int) $year <= 2100) {
            return (int) $year;
        }

        return $current;
    }
}
