<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuranReviewSession;
use App\Models\QuranReviewWord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuranReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = QuranReviewSession::query()
            ->with(['student:id,name', 'teacher:id,name', 'surah:id,name_arabic'])
            ->when($request->teacher_id, fn ($q) => $q->where('teacher_id', $request->teacher_id))
            ->when($request->student_id, fn ($q) => $q->where('student_id', $request->student_id))
            ->when($request->surah_id, fn ($q) => $q->where('surah_id', $request->surah_id))
            ->when($request->date_from, fn ($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('date', '<=', $request->date_to))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($sessions);
    }

    public function show(string $id): JsonResponse
    {
        $session = QuranReviewSession::query()
            ->with([
                'student:id,name',
                'teacher:id,name',
                'surah:id,name_arabic',
                'words' => fn ($q) => $q->orderBy('ayah_id')->orderBy('word_position'),
                'words.ayah:id,ayah_number',
            ])
            ->findOrFail($id);

        return response()->json($session);
    }

    public function statistics(): JsonResponse
    {
        $totalSessions = QuranReviewSession::query()->count();
        $totalStudents = QuranReviewSession::query()->distinct('student_id')->count('student_id');
        $avgMastery = QuranReviewSession::query()->avg('mastery_percentage') ?? 0;

        $topSurahs = QuranReviewSession::query()
            ->join('quran_surahs', 'quran_review_sessions.surah_id', '=', 'quran_surahs.id')
            ->selectRaw('quran_surahs.name_arabic, COUNT(*) as session_count, AVG(quran_review_sessions.mastery_percentage) as avg_mastery')
            ->groupBy('quran_surahs.id', 'quran_surahs.name_arabic')
            ->orderByDesc('session_count')
            ->limit(10)
            ->get();

        $errorTotals = [
            'incorrect' => QuranReviewSession::query()->sum('incorrect_words'),
            'hesitation' => QuranReviewSession::query()->sum('hesitation_words'),
            'tajweed_error' => QuranReviewSession::query()->sum('tajweed_error_words'),
            'added' => QuranReviewSession::query()->sum('added_words'),
            'forgotten' => QuranReviewSession::query()->sum('forgotten_words'),
        ];

        $studentRankings = QuranReviewSession::query()
            ->join('students', 'quran_review_sessions.student_id', '=', 'students.id')
            ->selectRaw('students.id, students.name, COUNT(*) as session_count, AVG(quran_review_sessions.mastery_percentage) as avg_mastery')
            ->groupBy('students.id', 'students.name')
            ->orderByDesc('avg_mastery')
            ->limit(10)
            ->get();

        return response()->json([
            'total_sessions' => $totalSessions,
            'total_students' => $totalStudents,
            'average_mastery' => round($avgMastery, 2),
            'top_surahs' => $topSurahs,
            'error_totals' => $errorTotals,
            'student_rankings' => $studentRankings,
        ]);
    }

    public function studentReport(string $studentId): JsonResponse
    {
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

        return response()->json([
            'sessions' => $sessions,
            'error_stats' => [
                'incorrect' => $sessions->sum('incorrect_words'),
                'hesitation' => $sessions->sum('hesitation_words'),
                'tajweed_error' => $sessions->sum('tajweed_error_words'),
                'added' => $sessions->sum('added_words'),
                'forgotten' => $sessions->sum('forgotten_words'),
            ],
            'average_mastery' => $sessions->count() > 0
                ? round($sessions->avg('mastery_percentage'), 2)
                : 0,
            'needs_review_words' => $allWords,
        ]);
    }
}
