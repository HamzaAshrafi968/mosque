<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\QuranReviewSessionRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\QuranReviewSessionResource;
use App\Http\Resources\Api\V1\QuranReviewWordResource;
use App\Models\QuranReviewWord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuranReviewController extends BaseApiController
{
    public function __construct(
        private readonly QuranReviewSessionRepositoryInterface $sessionRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $sessions = $this->sessionRepository->paginateWithFilters([
            'teacher_id' => $request->input('teacher_id'),
            'student_id' => $request->input('student_id'),
            'surah_id' => $request->input('surah_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ], $request->input('per_page', 20));

        return $this->paginated(QuranReviewSessionResource::collection($sessions));
    }

    public function show(string $id): JsonResponse
    {
        $session = $this->sessionRepository->findWithRelations($id);

        if (! $session) {
            return $this->notFound('جلسة التسميع غير موجودة');
        }

        return $this->success(new QuranReviewSessionResource($session));
    }

    public function statistics(): JsonResponse
    {
        return $this->success($this->sessionRepository->getStatistics());
    }

    public function studentReport(string $studentId): JsonResponse
    {
        $sessions = $this->sessionRepository->getByStudent($studentId);

        $allWords = QuranReviewWord::query()
            ->whereHas('reviewSession', fn ($q) => $q->where('student_id', $studentId))
            ->where('status', '!=', 'correct')
            ->where('status', '!=', 'unreviewed')
            ->with(['reviewSession:id,date,surah_id', 'reviewSession.surah:id,name_arabic', 'ayah:id,ayah_number,surah_id'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return $this->success([
            'sessions' => QuranReviewSessionResource::collection($sessions),
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
            'needs_review_words' => QuranReviewWordResource::collection($allWords),
        ]);
    }
}
