<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Models\QuranAyah;
use App\Models\QuranReviewSession;
use App\Models\QuranReviewWord;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuranReviewController extends BaseTeacherController
{
    public function index(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $sessions = QuranReviewSession::query()
            ->with(['student:id,name', 'surah:id,name_arabic'])
            ->where('teacher_id', $teacher->id)
            ->when($request->student_id, fn ($q) => $q->where('student_id', $request->student_id))
            ->when($request->surah_id, fn ($q) => $q->where('surah_id', $request->surah_id))
            ->when($request->date_from, fn ($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('date', '<=', $request->date_to))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($sessions);
    }

    public function store(Request $request): JsonResponse
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
            QuranReviewSession::create([
                'id' => $sessionId,
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

            foreach (array_chunk($wordRows, 500) as $chunk) {
                QuranReviewWord::insert($chunk);
            }
        });

        return response()->json([
            'session_id' => $sessionId,
            'mastery_percentage' => $masteryPercentage,
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
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

        return response()->json($session);
    }

    public function studentReport(Request $request, string $studentId): JsonResponse
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

        return response()->json([
            'student' => $student,
            'sessions' => $sessions,
            'error_stats' => $errorStats,
            'average_mastery' => $avgMastery,
            'needs_review_words' => $allWords,
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
}
