<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuranReviewSession;
use App\Models\QuranReviewWord;
use App\Models\QuranSurah;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuranReviewController extends Controller
{
    public function index(Request $request): View
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
            ->paginate(20);

        return view('admin.quran-review.index', [
            'sessions' => $sessions,
            'teachers' => Teacher::orderBy('name')->get(['id', 'name']),
            'students' => Student::active()->orderBy('name')->get(['id', 'name']),
            'surahs' => QuranSurah::orderBy('sort_order')->get(['id', 'name_arabic']),
        ]);
    }

    public function show(string $id): View
    {
        $session = QuranReviewSession::query()
            ->with([
                'student:id,name',
                'teacher:id,name',
                'surah:id,name_arabic',
                'words' => fn ($q) => $q->orderByAyah(),
                'words.ayah:id,ayah_number',
            ])
            ->findOrFail($id);

        return view('admin.quran-review.show', [
            'session' => $session,
        ]);
    }

    public function statistics(Request $request): View
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

        return view('admin.quran-review.statistics', [
            'totalSessions' => $totalSessions,
            'totalStudents' => $totalStudents,
            'avgMastery' => round($avgMastery, 2),
            'topSurahs' => $topSurahs,
            'errorTotals' => $errorTotals,
            'studentRankings' => $studentRankings,
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

        return view('admin.quran-review.student-report', [
            'student' => $student,
            'sessions' => $sessions,
            'allWords' => $allWords,
            'errorStats' => $errorStats,
            'avgMastery' => $avgMastery,
        ]);
    }
}
