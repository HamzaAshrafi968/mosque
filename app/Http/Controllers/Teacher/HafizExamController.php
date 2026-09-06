<?php

namespace App\Http\Controllers\Teacher;

use App\Models\HafizExamRevision;
use App\Models\HafizMonthlyExam;
use App\Models\HafizProfile;
use App\Models\QuranSurah;
use App\Services\AuditLogger;
use App\Services\QuranProgramService;
use App\Services\QuranScopeService;
use App\Support\QuranProgramSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * الاختبارات الشهرية للحفاظ — يظهر للمعلم فقط حفاظه (ضمن نطاقه) الذين
 * لم يُسندوا لمشرف آخر، ويتولى هو تسجيل النتيجة والإشراف عليهم.
 */
class HafizExamController extends BaseTeacherController
{
    public function __construct(
        private readonly QuranScopeService $scope,
        private readonly QuranProgramService $programs,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);
        $month = $this->validMonth($request->input('month'));

        $ids = $this->scope->studentIdsFor($teacher);

        $hafizIds = HafizProfile::query()->whereIn('student_id', $ids)->pluck('student_id');

        $this->programs->ensureMonthlyExamRows($hafizIds, $month, $request->user());

        $exams = HafizMonthlyExam::query()
            ->with(['student:id,name', 'student.classroom:id,name', 'revisions'])
            ->where('month', $month)
            ->whereIn('student_id', $hafizIds)
            ->where(fn ($q) => $q->whereNull('supervisor_id')->orWhere('supervisor_id', $teacher->id))
            ->orderBy('student_id')
            ->get();

        return view('teacher.quran.exams.index', [
            'exams' => $exams,
            'month' => $month,
            'previousMonth' => QuranProgramSettings::previousMonth($month),
            'nextMonth' => QuranProgramSettings::nextMonth($month),
            'monthLabel' => fn (string $m) => QuranProgramSettings::monthLabel($m),
        ]);
    }

    public function show(Request $request, HafizMonthlyExam $exam): View
    {
        $teacher = $this->currentTeacher($request);
        $this->assertCanManageExam($teacher, $exam);

        $exam->load(['student:id,name,classroom_id', 'student.classroom:id,name', 'revisions.fromSurah:id,name_arabic', 'revisions.toSurah:id,name_arabic']);

        return view('teacher.quran.exams.show', [
            'exam' => $exam,
            'surahs' => QuranSurah::orderBy('sort_order')->get(['id', 'name_arabic']),
            'passMark' => QuranProgramSettings::HAFIZ_EXAM_PASS_MARK,
            'monthLabel' => fn (string $m) => QuranProgramSettings::monthLabel($m),
        ]);
    }

    public function grade(Request $request, HafizMonthlyExam $exam): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->assertCanManageExam($teacher, $exam);

        $data = $request->validate([
            'grade' => ['required', 'numeric', 'min:0', 'max:100'],
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

        $data['supervisor_id'] = $teacher->id;
        $data['notes'] = $data['notes'] ?? null;

        if ((float) $data['grade'] < QuranProgramSettings::HAFIZ_EXAM_PASS_MARK
            && $exam->revisions()->where('status', '!=', 'approved')->count() === 0
            && ! $this->hasRevisionPayload($request)) {
            throw ValidationException::withMessages([
                'grade' => ['نتيجة راسبة تتطلب تحديد الأجزاء المطلوب إعادتها (سجل الأجزاء أدناه)'],
            ]);
        }

        $this->programs->gradeMonthlyExam($exam, $data, $request->user());
        $this->storeRevisions($exam, $request, $request->user());

        return redirect()
            ->route('teacher.quran.exams.show', $exam)
            ->with('success', 'تم تسجيل نتيجة الشهر مع حفظ تاريخ التقييمات السابقة');
    }

    public function storeRevision(Request $request, HafizMonthlyExam $exam): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->assertCanManageExam($teacher, $exam);

        $data = $this->revisionValidated($request);

        if (! $data['juz'] && ! $data['from_surah'] && ! $data['to_surah'] && ! $data['amount']) {
            throw ValidationException::withMessages(['juz' => ['حدد الجزء أو السورة/المقدار المطلوب إعادته']]);
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

    /** إكمال إعادة التلاوة من قبل المعلم المشرف. */
    public function completeRevision(Request $request, HafizExamRevision $revision): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->assertCanManageExam($teacher, $revision->exam);

        if ($revision->status?->value !== 'pending') {
            abort(422);
        }

        $before = $revision->getAttributes();
        $revision->update(['status' => 'completed']);
        $this->audit->logModel('hafiz_exam.revision_completed', $revision, $before, actor: $request->user());

        return back()->with('success', 'تم تأكيد إكمال إعادة الجزء — بانتظار الاعتماد من الإدارة');
    }

    private function assertCanManageExam($teacher, HafizMonthlyExam $exam): void
    {
        $this->scope->assertCanManageStudent($teacher, $exam->student);

        if ($exam->supervisor_id !== null && $exam->supervisor_id !== $teacher->id) {
            abort(403, 'هذا الاختبار بإشراف معلم آخر');
        }
    }

    private function storeRevisions(HafizMonthlyExam $exam, Request $request, $actor): void
    {
        foreach ($request->input('revisions', []) as $row) {
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

    private function hasRevisionPayload(Request $request): bool
    {
        foreach ($request->input('revisions', []) as $row) {
            if (($row['juz'] ?? null) || ($row['from_surah'] ?? null) || ($row['to_surah'] ?? null) || ($row['amount'] ?? null)) {
                return true;
            }
        }

        return false;
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

    private function validMonth(?string $month): string
    {
        if ($month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return $month;
        }

        return QuranProgramSettings::monthOf(now());
    }
}
