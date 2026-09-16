<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\Teacher\QuranReview\CreatePageQuranReviewSessionAction;
use App\Models\QuranAyah;
use App\Models\QuranReviewSession;
use App\Models\QuranReviewWord;
use App\Models\QuranSurah;
use App\Models\Student;
use App\Services\QuranPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuranReviewController extends BaseTeacherController
{
    public function index(Request $request): RedirectResponse
    {
        // دُمج «الاستماع مع المعلم» في مركز «دفعات الحفظ».
        return redirect()->route('teacher.quran.batches.index');
    }

    public function create(Request $request, QuranPageService $pages): View
    {
        $studentId = $request->input('student_id');
        $fromPage = (int) $request->input('from_page');
        $toPage = (int) $request->input('to_page');

        $students = Student::query()->active()->orderBy('name')->get(['id', 'name']);

        $pagesSeeded = $pages->pagesAreSeeded();
        $pageData = collect();
        $rangeError = null;

        if ($fromPage || $toPage) {
            if ($fromPage < 1 || $toPage > QuranPageService::MAX_PAGE || $toPage < $fromPage) {
                $rangeError = 'نطاق الصفحات غير صحيح: يجب أن يكون بين ١ و ٦٠٤ وبترتيب صحيح';
            } elseif (($toPage - $fromPage + 1) > QuranPageService::MAX_REVIEW_PAGES) {
                $rangeError = 'الحد الأقصى لعدد صفحات الاستماع الواحدة هو '.QuranPageService::MAX_REVIEW_PAGES.' صفحات';
            } elseif (! $pagesSeeded) {
                $rangeError = 'بيانات الصفحات غير مهيأة — شغّل: php artisan quran:pages';
            } else {
                $pageData = $pages->pagesForRange($fromPage, $toPage);
            }
        }

        return view('teacher.quran-review.create', [
            'students' => $students,
            'pages' => $pageData,
            'pagesSeeded' => $pagesSeeded,
            'rangeError' => $rangeError,
            'studentId' => $studentId,
            'fromPage' => $fromPage ?: null,
            'toPage' => $toPage ?: null,
            'date' => $request->input('date', now()->toDateString()),
            'notes' => $request->input('notes'),
        ]);
    }

    public function store(Request $request, CreatePageQuranReviewSessionAction $action): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'from_page' => ['required', 'integer', 'min:1', 'max:'.QuranPageService::MAX_PAGE],
            'to_page' => ['required', 'integer', 'min:1', 'max:'.QuranPageService::MAX_PAGE, 'gte:from_page'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'word_statuses' => ['required', 'array'],
            'word_statuses.*' => ['required', 'string', 'in:correct,incorrect,hesitation,tajweed_error,added,forgotten,unreviewed'],
            'word_notes' => ['nullable', 'array'],
        ]);

        $teacher = $this->currentTeacher($request);

        $result = $action->execute(
            $data,
            $teacher->id,
            $request->user()->tenant_id,
            $request
        );

        return redirect()
            ->route('teacher.quran-review.show', $result['session_id'])
            ->with('success', 'تم حفظ الاستماع بنجاح');
    }

    public function show(Request $request, string $id, QuranPageService $pages): View
    {
        $teacher = $this->currentTeacher($request);

        $session = QuranReviewSession::query()
            ->with([
                'student:id,name',
                'surah:id,name_arabic',
                'teacher:id,name',
                'words' => fn ($q) => $q->orderByAyah(),
                'words.ayah:id,ayah_number',
            ])
            ->where('teacher_id', $teacher->id)
            ->findOrFail($id);

        $pageData = collect();
        $statuses = [];

        if ($session->isPageBased() && $pages->pagesAreSeeded()) {
            $pageData = $pages->pagesForRange($session->from_page, $session->to_page);

            foreach ($session->words as $word) {
                $statuses[$word->ayah_id.':'.$word->word_position] = $word->status;
            }
        }

        return view('teacher.quran-review.show', [
            'session' => $session,
            'pages' => $pageData,
            'statuses' => $statuses,
        ]);
    }

    public function studentReport(Request $request, string $studentId): View
    {
        $teacher = $this->currentTeacher($request);

        $student = Student::findOrFail($studentId);

        $sessions = QuranReviewSession::query()
            ->with('surah:id,name_arabic')
            ->where('student_id', $studentId)
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('date')
            ->get();

        $allWords = QuranReviewWord::query()
            ->whereHas('reviewSession', fn ($q) => $q
                ->where('student_id', $studentId)
                ->where('teacher_id', $teacher->id))
            ->where('status', '!=', 'correct')
            ->where('status', '!=', 'unreviewed')
            ->with(['reviewSession:id,date,surah_id', 'reviewSession.surah:id,name_arabic', 'ayah:id,ayah_number,surah_id'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $errorStats = [
            'incorrect' => $sessions->sum('incorrect_words'),
            'hesitation' => $sessions->sum('hesitation_words'),
            'tajweed_error' => $sessions->sum('tajweed_error_words'),
            'added' => $sessions->sum('added_words'),
            'forgotten' => $sessions->sum('forgotten_words'),
        ];

        $avgMastery = $sessions->count() > 0
            ? round($sessions->avg('mastery_percentage'), 2)
            : 0;

        return view('teacher.quran-review.student-report', [
            'student' => $student,
            'sessions' => $sessions,
            'allWords' => $allWords,
            'errorStats' => $errorStats,
            'avgMastery' => $avgMastery,
        ]);
    }

    public function getAyahs(Request $request): JsonResponse
    {
        $request->validate([
            'surah_id' => ['required', 'uuid', 'exists:quran_surahs,id'],
            'from_ayah' => ['required', 'integer', 'min:1'],
            'to_ayah' => ['required', 'integer', 'min:1'],
        ]);

        $surah = QuranSurah::find($request->surah_id);

        if (! $surah
            || $request->from_ayah > $surah->num_ayahs
            || $request->to_ayah > $surah->num_ayahs
            || $request->to_ayah < $request->from_ayah
        ) {
            return response()->json([
                'message' => 'نطاق الآيات غير صحيح',
                'errors' => ['to_ayah' => ['نطاق الآيات يجب أن يكون ضمن حدود السورة وبترتيب صحيح']],
            ], 422);
        }

        $ayahs = QuranAyah::query()
            ->where('surah_id', $request->surah_id)
            ->whereBetween('ayah_number', [$request->from_ayah, $request->to_ayah])
            ->orderBy('ayah_number')
            ->get(['id', 'ayah_number', 'text']);

        $result = $ayahs->map(function ($ayah) {
            $words = explode(' ', $ayah->text);

            return [
                'id' => $ayah->id,
                'ayah_number' => $ayah->ayah_number,
                'text' => $ayah->text,
                'words' => array_values(array_filter($words, fn ($w) => $w !== '')),
            ];
        });

        return response()->json($result);
    }
}
