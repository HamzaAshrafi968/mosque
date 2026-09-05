<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\QuranReviewSessionRepositoryInterface;
use App\Models\QuranReviewSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class QuranReviewSessionRepository extends BaseRepository implements QuranReviewSessionRepositoryInterface
{
    public function __construct(QuranReviewSession $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['student:id,name', 'teacher:id,name', 'surah:id,name_arabic'])
            ->when(! empty($filters['teacher_id']), fn ($q) => $q->where('teacher_id', $filters['teacher_id']))
            ->when(! empty($filters['student_id']), fn ($q) => $q->where('student_id', $filters['student_id']))
            ->when(! empty($filters['surah_id']), fn ($q) => $q->where('surah_id', $filters['surah_id']))
            ->when(! empty($filters['date_from']), fn ($q) => $q->whereDate('date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->whereDate('date', '<=', $filters['date_to']))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findWithRelations(string $id, ?string $teacherId = null): ?QuranReviewSession
    {
        return $this->model
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->with([
                'student:id,name',
                'teacher:id,name',
                'surah:id,name_arabic',
                'words' => fn ($q) => $q->orderByAyah(),
                'words.ayah:id,ayah_number',
            ])
            ->find($id);
    }

    public function getStatistics(): array
    {
        $totalSessions = $this->model->count();
        $totalStudents = $this->model->distinct('student_id')->count('student_id');
        $avgMastery = $this->model->avg('mastery_percentage') ?? 0;

        $topSurahs = $this->model
            ->join('quran_surahs', 'quran_review_sessions.surah_id', '=', 'quran_surahs.id')
            ->selectRaw('quran_surahs.name_arabic, COUNT(*) as session_count, AVG(quran_review_sessions.mastery_percentage) as avg_mastery')
            ->groupBy('quran_surahs.id', 'quran_surahs.name_arabic')
            ->orderByDesc('session_count')
            ->limit(10)
            ->get();

        $errorTotals = [
            'incorrect' => $this->model->sum('incorrect_words'),
            'hesitation' => $this->model->sum('hesitation_words'),
            'tajweed_error' => $this->model->sum('tajweed_error_words'),
            'added' => $this->model->sum('added_words'),
            'forgotten' => $this->model->sum('forgotten_words'),
        ];

        $studentRankings = $this->model
            ->join('students', 'quran_review_sessions.student_id', '=', 'students.id')
            ->selectRaw('students.id, students.name, COUNT(*) as session_count, AVG(quran_review_sessions.mastery_percentage) as avg_mastery')
            ->groupBy('students.id', 'students.name')
            ->orderByDesc('avg_mastery')
            ->limit(10)
            ->get();

        return [
            'total_sessions' => $totalSessions,
            'total_students' => $totalStudents,
            'average_mastery' => round($avgMastery, 2),
            'top_surahs' => $topSurahs,
            'error_totals' => $errorTotals,
            'student_rankings' => $studentRankings,
        ];
    }

    public function getByStudent(string $studentId): Collection
    {
        return $this->model
            ->with('surah:id,name_arabic')
            ->where('student_id', $studentId)
            ->orderByDesc('date')
            ->get();
    }
}
