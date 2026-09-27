<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuranListeningTestResult;
use App\Enums\QuranTasmeeResult;
use App\Http\Controllers\Concerns\ListsQuranBatchStudents;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\QuranKhamsaReview;
use App\Models\QuranListeningTest;
use App\Models\QuranMemorizationBatch;
use App\Models\QuranReviewSession;
use App\Models\Section;
use App\Models\Student;
use App\Services\QuranAudioService;
use App\Services\QuranBatchPanelService;
use App\Services\QuranMemorizationGatingService;
use App\Services\QuranSettingsService;
use App\Services\QuranTeacherSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «دفعات الحفظ» — مركز القرآن الموحّد لمدير الجامع:
 * دورة كل طالب (جزآن → مراجعة 5 → اختبار بحد نجاح الجامع → الدفعة التالية)،
 * مع تضمين «مراجعة 5» و«خطة الاستماع» و«الاستماع مع المعلم» في صفحة واحدة.
 */
class QuranBatchController extends Controller
{
    use ListsQuranBatchStudents;

    public function __construct(
        private readonly QuranMemorizationGatingService $gating,
        private readonly QuranSettingsService $settings,
        private readonly QuranAudioService $audio,
        private readonly QuranBatchPanelService $panel,
        private readonly QuranTeacherSessionService $sessions,
    ) {}

    public function index(Request $request): View
    {
        $selected = $request->filled('student_id')
            ? Student::query()->find($request->input('student_id'))
            : null;

        [$students, $summaries] = $this->paginateBatchStudents(
            Student::query()
                ->active()
                ->with(['classroom:id,name', 'section:id,name', 'studySession:id,name,gender'])
                ->search($request->string('q')->toString())
                ->when($request->filled('classroom_id'), fn ($query) => $query->where('classroom_id', $request->input('classroom_id')))
                ->when($request->filled('section_id'), fn ($query) => $query->where('section_id', $request->input('section_id')))
                ->orderByStudySession(),
            $this->panel,
            $request->string('status')->toString() ?: null,
        );

        $cycle = $selected
            ? $this->panel->forStudent(
                $selected,
                $request->user(),
                reviewShowUrl: fn (QuranReviewSession $session) => route('admin.quran-review.show', $session->id),
            )
            : QuranBatchPanelService::empty();

        return view('admin.quran.batches.index', array_merge($cycle, [
            'students' => $students,
            'summaries' => $summaries,
            'classrooms' => Classroom::query()->orderBy('name')->get(['id', 'name']),
            'sections' => Section::query()
                ->when($request->filled('classroom_id'), fn ($query) => $query->where('classroom_id', $request->input('classroom_id')))
                ->with('classroom:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'classroom_id']),
            'selectedStudent' => $selected,
            'reciters' => $this->audio->reciters(),
            'khamsaReviewRoute' => fn (QuranKhamsaReview $review) => route('admin.quran.khamsa.review', $review),
            'tasmeeResults' => QuranTasmeeResult::cases(),
            'statuses' => $this->batchStatusOptions(),
            'minimumPassingPercentage' => $this->settings->minimumPassingPercentage(),
        ]));
    }

    /** بوابة «التسميع مع المعلم» الموحدة: اختيار نوع الجلسة قبل فتح الـWorkflow المناسب. */
    public function sessionStart(Request $request): View
    {
        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
        ]);

        $student = Student::query()->findOrFail($data['student_id']);

        return view('quran.batches.session-type', [
            'student' => $student,
            'currentBatch' => $this->gating->currentBatch($student),
            'options' => $this->sessions->typesFor($request->user(), $student, 'admin.'),
            'backUrl' => route('admin.quran.batches.index', ['student_id' => $student->id]),
        ]);
    }

    public function repeat(Request $request, QuranMemorizationBatch $batch): RedirectResponse
    {
        $this->gating->regenerateReview($batch, $request->user());

        return back()->with('success', 'تم إنشاء مراجعة 5 جديدة لـ'.$batch->label());
    }

    /** تسجيل نتيجة الاختبار التراكمي: نتيجة لكل جزء (ناجح/يحتاج إعادة). */
    public function test(Request $request, QuranMemorizationBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*' => ['required', Rule::enum(QuranListeningTestResult::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $test = $this->gating->recordCumulativeTest($batch, $data['results'], $request->user(), $data['notes'] ?? null);

        $score = $this->formatPercent((float) $test->score);

        if ($test->isPass()) {
            return back()->with('success', 'ما شاء الله — نجاح في الاختبار التراكمي بنسبة '.$score.'%. تم تثبيت الدفعة وفتح الدفعة التالية إن وُجدت.');
        }

        $failed = $test->items()
            ->where('result', QuranListeningTestResult::Fail)
            ->orderBy('juz')
            ->pluck('juz')
            ->implode('، ');

        return back()->with('success', 'نتيجة الاختبار التراكمي: '.$score.'% — رسب في الأجزاء: '.$failed.' — تم إنشاء خمسات إعادة للأجزاء الراسبة.');
    }

    /** الاختبار المباشر للأجزاء المحفوظة مسبقاً (بدون اشتراط إنهاء مراجعة 5). */
    public function placementTest(Request $request, QuranMemorizationBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*' => ['required', Rule::enum(QuranListeningTestResult::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $tests = $this->gating->recordPlacementTest($batch, $data['results'], $request->user(), $data['notes'] ?? null);
        $summary = $this->gating->placementTestSummary($tests);

        if ($summary['passed']) {
            return back()->with('success', 'ما شاء الله — نجاح في الاختبار المباشر للأجزاء '.$summary['scope_label'].' بنسبة '.$this->formatPercent($summary['score']).'%. تم تثبيت '.$summary['passed_batches'].' دفعة وفتح الدفعة التالية إن وُجدت.');
        }

        $retakeCreated = $tests
            ->reject(fn (QuranListeningTest $test) => $test->isPass())
            ->contains(fn (QuranListeningTest $test) => $test->batch?->retake_review_id !== null);

        $failed = $summary['failed_juz'] === [] ? '—' : implode('، ', $summary['failed_juz']);

        return back()->with('success', $retakeCreated
            ? 'نتيجة الاختبار المباشر: '.$this->formatPercent($summary['score']).'% — ثُبّتت '.$summary['passed_batches'].' دفعة — رسب في الأجزاء: '.$failed.' — أُنشئت خمسات إعادة للأجزاء الراسبة.'
            : 'نتيجة الاختبار المباشر: '.$this->formatPercent($summary['score']).'% — ثُبّتت '.$summary['passed_batches'].' دفعة — رسب في الأجزاء: '.$failed.' — تعذّر إنشاء خمسات الإعادة تلقائياً؛ تأكد من دوام الطالب ووجود أستاذ نشط فيه ثم استخدم أزرار الإعادة.');
    }

    /** خيارا ما بعد الرسوب: خمسات إعادة للأجزاء الراسبة فقط أو إعادة كامل النطاق. */
    public function retake(Request $request, QuranMemorizationBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['failed', 'full'])],
        ]);

        $juz = $data['mode'] === 'full'
            ? $this->gating->cumulativeJuzNumbers($batch)
            : $this->gating->failedJuzNumbers($batch);

        if ($juz === []) {
            return back()->with('success', 'لا توجد أجزاء راسبة — لا حاجة لخمسات إعادة.');
        }

        $review = $this->gating->createRetakeReview($batch, $juz, $request->user());

        if (! $review) {
            return back()->with('success', 'تعذّر إنشاء خمسات الإعادة — تأكد من وجود دوام للطالب وأستاذ نشط فيه.');
        }

        return back()->with('success', $data['mode'] === 'full'
            ? 'تم تخصيص خمسات إعادة للأجزاء كاملة (1–'.$batch->to_juz.')'
            : 'تم تخصيص خمسات إعادة للأجزاء الراسبة: '.implode('، ', $juz));
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
