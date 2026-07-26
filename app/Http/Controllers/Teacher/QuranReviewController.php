<?php

namespace App\Http\Controllers\Teacher;

use App\Models\QuranAyah;
use App\Models\QuranReviewSession;
use App\Models\QuranReviewWord;
use App\Models\QuranSurah;
use App\Models\RewardPoint;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuranReviewController extends BaseTeacherController
{
    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $sessions = QuranReviewSession::query()
            ->with(['student:id,name', 'surah:id,name_arabic'])
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate(20);

        $students = Student::query()->active()->orderBy('name')->get(['id', 'name']);
        $surahs = QuranSurah::orderBy('sort_order')->get(['id', 'name_arabic']);

        return view('teacher.quran-review.index', [
            'sessions' => $sessions,
            'students' => $students,
            'surahs' => $surahs,
        ]);
    }

    public function create(Request $request): View
    {
        $surahId = $request->input('surah_id');
        $studentId = $request->input('student_id');
        $fromAyah = (int) $request->input('from_ayah', 1);
        $toAyah = (int) $request->input('to_ayah');

        $students = Student::query()->active()->orderBy('name')->get(['id', 'name']);
        $surahs = QuranSurah::orderBy('sort_order')->get(['id', 'name_arabic', 'num_ayahs']);

        $ayahs = collect();
        if ($surahId && $toAyah >= $fromAyah) {
            $ayahs = QuranAyah::query()
                ->with('surah:id,name_arabic')
                ->where('surah_id', $surahId)
                ->whereBetween('ayah_number', [$fromAyah, $toAyah])
                ->orderBy('ayah_number')
                ->get(['id', 'surah_id', 'ayah_number', 'text', 'text_simple']);
        }

        $surah = $surahId ? QuranSurah::find($surahId) : null;

        return view('teacher.quran-review.create', [
            'students' => $students,
            'surahs' => $surahs,
            'surah' => $surah,
            'ayahs' => $ayahs,
            'surahId' => $surahId,
            'studentId' => $studentId,
            'fromAyah' => $fromAyah,
            'toAyah' => $toAyah ?: ($surah ? $surah->num_ayahs : 7),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'surah_id' => ['required', 'uuid', 'exists:quran_surahs,id'],
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'from_ayah' => ['required', 'integer', 'min:1'],
            'to_ayah' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'word_statuses' => ['required', 'array'],
            'word_statuses.*' => ['required', 'string', 'in:correct,incorrect,hesitation,tajweed_error,added,forgotten,unreviewed'],
            'word_notes' => ['nullable', 'array'],
        ]);

        $teacher = $this->currentTeacher($request);
        $tenantId = $request->user()->tenant_id;
        $now = now();

        $sessionId = (string) Str::uuid();

        $ayahs = QuranAyah::query()
            ->where('surah_id', $data['surah_id'])
            ->whereBetween('ayah_number', [$data['from_ayah'], $data['to_ayah']])
            ->orderBy('ayah_number')
            ->get(['id', 'ayah_number', 'text_simple']);

        $wordRows = [];
        $stats = [
            'correct' => 0,
            'incorrect' => 0,
            'hesitation' => 0,
            'tajweed_error' => 0,
            'added' => 0,
            'forgotten' => 0,
        ];
        $totalWords = 0;

        $wordIndex = 0;
        foreach ($ayahs as $ayah) {
            $words = explode(' ', $ayah->text_simple);
            foreach ($words as $pos => $word) {
                if ($word === '') {
                    continue;
                }
                $status = $data['word_statuses'][$wordIndex] ?? 'unreviewed';
                $wordNotes = $data['word_notes'][$wordIndex] ?? null;
                $errorType = null;

                if (in_array($status, ['incorrect', 'hesitation', 'tajweed_error', 'added', 'forgotten'])) {
                    $errorType = $status === 'incorrect' ? 'pronunciation'
                        : ($status === 'tajweed_error' ? 'tajweed' : $status);
                }

                $wordRows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'review_session_id' => $sessionId,
                    'ayah_id' => $ayah->id,
                    'word_position' => $pos,
                    'word_text' => $word,
                    'status' => $status,
                    'error_type' => $errorType,
                    'notes' => $wordNotes,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (isset($stats[$status])) {
                    $stats[$status]++;
                }
                $totalWords++;
                $wordIndex++;
            }
        }

        $masteryPercentage = $totalWords > 0
            ? round(($stats['correct'] / $totalWords) * 100, 2)
            : 100;

        DB::transaction(function () use (
            $sessionId, $data, $teacher, $tenantId, $stats, $totalWords, $masteryPercentage, $wordRows
        ) {
            $session = new QuranReviewSession([
                'tenant_id' => $tenantId,
                'teacher_id' => $teacher->id,
                'student_id' => $data['student_id'],
                'surah_id' => $data['surah_id'],
                'from_ayah' => $data['from_ayah'],
                'to_ayah' => $data['to_ayah'],
                'total_words' => $totalWords,
                'correct_words' => $stats['correct'],
                'incorrect_words' => $stats['incorrect'],
                'hesitation_words' => $stats['hesitation'],
                'tajweed_error_words' => $stats['tajweed_error'],
                'added_words' => $stats['added'],
                'forgotten_words' => $stats['forgotten'],
                'mastery_percentage' => $masteryPercentage,
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
            ]);
            $session->id = $sessionId;
            $session->save();

            foreach (array_chunk($wordRows, 500) as $chunk) {
                QuranReviewWord::insert($chunk);
            }
        });

        $this->awardPointsForSession($sessionId, $data['student_id'], $masteryPercentage, $request);

        return redirect()
            ->route('teacher.quran-review.show', $sessionId)
            ->with('success', 'تم حفظ المراجعة بنجاح');
    }

    public function show(Request $request, string $id): View
    {
        $session = QuranReviewSession::query()
            ->with([
                'student:id,name',
                'surah:id,name_arabic',
                'teacher:id,name',
                'words' => fn ($q) => $q->orderBy('ayah_id')->orderBy('word_position'),
                'words.ayah:id,ayah_number',
            ])
            ->findOrFail($id);

        return view('teacher.quran-review.show', [
            'session' => $session,
        ]);
    }

    public function studentReport(Request $request, string $studentId): View
    {
        $student = Student::findOrFail($studentId);

        $sessions = QuranReviewSession::query()
            ->with('surah:id,name_arabic')
            ->where('student_id', $studentId)
            ->orderByDesc('date')
            ->get();

        $allWords = QuranReviewWord::query()
            ->whereHas('reviewSession', fn ($q) => $q->where('student_id', $studentId))
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

        $ayahs = QuranAyah::query()
            ->where('surah_id', $request->surah_id)
            ->whereBetween('ayah_number', [$request->from_ayah, $request->to_ayah])
            ->orderBy('ayah_number')
            ->get(['id', 'ayah_number', 'text', 'text_simple']);

        $result = $ayahs->map(function ($ayah) {
            $words = explode(' ', $ayah->text_simple);

            return [
                'id' => $ayah->id,
                'ayah_number' => $ayah->ayah_number,
                'text' => $ayah->text,
                'words' => array_values(array_filter($words, fn ($w) => $w !== '')),
            ];
        });

        return response()->json($result);
    }

    private function awardPointsForSession(string $sessionId, string $studentId, float $masteryPercentage, Request $request): void
    {
        $points = match (true) {
            $masteryPercentage >= 90 => 10,
            $masteryPercentage >= 80 => 7,
            $masteryPercentage >= 70 => 5,
            $masteryPercentage >= 60 => 3,
            $masteryPercentage < 60 => 1,
        };

        RewardPoint::create([
            'student_id' => $studentId,
            'awarded_by' => $request->user()->id,
            'quran_review_session_id' => $sessionId,
            'points' => $points,
            'reason' => 'نقاط تلقائية من التسميع',
            'type' => 'earned',
            'notes' => 'تم احتساب النقاط تلقائياً بناءً على نسبة الإتقان: ' . $masteryPercentage . '%',
        ]);
    }
}
