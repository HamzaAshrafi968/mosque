<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\QuranListeningTestResult;
use App\Enums\QuranMemorizationBatchStatus;
use App\Enums\QuranTasmeeResult;
use App\Models\QuranKhamsaReview;
use App\Models\QuranMemorizationBatch;
use App\Models\QuranReviewSession;
use App\Models\Student;
use App\Services\QuranAudioService;
use App\Services\QuranBatchPanelService;
use App\Services\QuranMemorizationGatingService;
use App\Services\QuranScopeService;
use App\Services\QuranSettingsService;
use App\Services\QuranTeacherSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «دفعات الحفظ» — مركز القرآن الموحّد للأستاذ: دورة دفعات طلابه ضمن نطاقه،
 * مع تضمين «مراجعة 5» و«خطة الاستماع» و«الاستماع مع المعلم» في صفحة واحدة.
 */
class QuranBatchController extends BaseTeacherController
{
    public function __construct(
        private readonly QuranMemorizationGatingService $gating,
        private readonly QuranScopeService $scope,
        private readonly QuranSettingsService $settings,
        private readonly QuranAudioService $audio,
        private readonly QuranBatchPanelService $panel,
        private readonly QuranTeacherSessionService $sessions,
    ) {}

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);
        $studentIds = $this->scope->studentIdsFor($teacher);

        $selected = null;

        if ($request->filled('student_id')) {
            $selected = Student::query()->find($request->input('student_id'));

            if ($selected) {
                $this->scope->assertCanManageStudent($teacher, $selected);
            }
        }

        $batches = QuranMemorizationBatch::query()
            ->with(['student:id,name', 'plan:id,title,status', 'review5:id,status', 'lastTest:id,result,score,passing_percentage'])
            ->whereIn('student_id', $studentIds)
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->input('student_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $cycle = $selected
            ? $this->panel->forStudent(
                $selected,
                $request->user(),
                teacherId: $teacher->id,
                reviewShowUrl: fn (QuranReviewSession $session) => route('teacher.quran-review.show', $session->id),
            )
            : QuranBatchPanelService::empty();

        return view('teacher.quran.batches.index', array_merge($cycle, [
            'batches' => $batches,
            'selectedStudent' => $selected,
            'reciters' => $this->audio->reciters(),
            'khamsaReviewRoute' => fn (QuranKhamsaReview $review) => route('teacher.quran.khamsa.review', $review),
            'tasmeeResults' => QuranTasmeeResult::cases(),
            'students' => $this->scope->studentsFor($teacher),
            'statuses' => QuranMemorizationBatchStatus::cases(),
            'minimumPassingPercentage' => $this->settings->minimumPassingPercentage(),
        ]));
    }

    /** بوابة «التسميع مع المعلم» الموحدة: اختيار نوع الجلسة قبل فتح الـWorkflow المناسب. */
    public function sessionStart(Request $request): View
    {
        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
        ]);

        $teacher = $this->currentTeacher($request);
        $student = Student::query()->findOrFail($data['student_id']);
        $this->scope->assertCanManageStudent($teacher, $student);

        return view('quran.batches.session-type', [
            'student' => $student,
            'currentBatch' => $this->gating->currentBatch($student),
            'options' => $this->sessions->typesFor($request->user(), $student, 'teacher.'),
            'backUrl' => route('teacher.quran.batches.index', ['student_id' => $student->id]),
        ]);
    }

    public function repeat(Request $request, QuranMemorizationBatch $batch): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->scope->assertCanManageStudent($teacher, $batch->student);

        $this->gating->regenerateReview($batch, $request->user());

        return back()->with('success', 'تم إنشاء مراجعة 5 جديدة لـ'.$batch->label());
    }

    /** تسجيل نتيجة الاختبار التراكمي: نتيجة لكل جزء (ناجح/يحتاج إعادة). */
    public function test(Request $request, QuranMemorizationBatch $batch): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->scope->assertCanManageStudent($teacher, $batch->student);

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
        $teacher = $this->currentTeacher($request);
        $this->scope->assertCanManageStudent($teacher, $batch->student);

        $data = $request->validate([
            'results' => ['required', 'array', 'min:1'],
            'results.*' => ['required', Rule::enum(QuranListeningTestResult::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $test = $this->gating->recordPlacementTest($batch, $data['results'], $request->user(), $data['notes'] ?? null);

        $score = $this->formatPercent((float) $test->score);

        if ($test->isPass()) {
            return back()->with('success', 'ما شاء الله — نجاح في الاختبار المباشر بنسبة '.$score.'%. تم تثبيت الأجزاء المحفوظة وفتح الدفعة التالية إن وُجدت.');
        }

        $failed = $test->items()
            ->where('result', QuranListeningTestResult::Fail)
            ->orderBy('juz')
            ->pluck('juz')
            ->implode('، ');

        $retakeCreated = $batch->refresh()->retake_review_id !== null;

        return back()->with('success', $retakeCreated
            ? 'نتيجة الاختبار المباشر: '.$score.'% — رسب في الأجزاء: '.$failed.' — أُنشئت خمسات إعادة للأجزاء الراسبة.'
            : 'نتيجة الاختبار المباشر: '.$score.'% — رسب في الأجزاء: '.$failed.' — تعذّر إنشاء خمسات الإعادة تلقائياً؛ تأكد من دوام الطالب ووجود أستاذ نشط فيه ثم استخدم أزرار الإعادة.');
    }

    /** خيارا ما بعد الرسوب: خمسات إعادة للأجزاء الراسبة فقط أو إعادة كامل النطاق. */
    public function retake(Request $request, QuranMemorizationBatch $batch): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->scope->assertCanManageStudent($teacher, $batch->student);

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
