<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Actions\Teacher\QuranReview\CreateQuranReviewSessionAction;
use App\Contracts\Repositories\QuranReviewSessionRepositoryInterface;
use App\Http\Requests\Api\V1\Teacher\GetAyahsRequest;
use App\Http\Requests\Api\V1\Teacher\StoreQuranReviewRequest;
use App\Http\Resources\Api\V1\QuranReviewSessionResource;
use App\Http\Resources\Api\V1\QuranReviewWordResource;
use App\Models\QuranAyah;
use App\Models\QuranReviewWord;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuranReviewController extends BaseTeacherController
{
    public function index(Request $request, QuranReviewSessionRepositoryInterface $sessionRepository): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $sessions = $sessionRepository->paginateWithFilters([
            'teacher_id' => $teacher->id,
            'student_id' => $request->input('student_id'),
            'surah_id' => $request->input('surah_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ], $request->input('per_page', 20));

        return $this->paginated(QuranReviewSessionResource::collection($sessions));
    }

    public function store(StoreQuranReviewRequest $request, CreateQuranReviewSessionAction $action): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        $tenantId = $request->user()->tenant_id;

        $result = $action->execute(
            $request->validated(),
            $teacher->id,
            $tenantId,
            $request
        );

        return $this->created($result);
    }

    public function show(Request $request, string $id, QuranReviewSessionRepositoryInterface $sessionRepository): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $session = $sessionRepository->findWithRelations($id, $teacher->id);

        if (! $session) {
            return $this->notFound('جلسة التسميع غير موجودة');
        }

        return $this->success(new QuranReviewSessionResource($session));
    }

    public function studentReport(Request $request, string $studentId): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $student = Student::findOrFail($studentId);

        $sessions = $student->quranReviewSessions()
            ->with('surah:id,name_arabic')
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

        return $this->success([
            'student' => $student,
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

    public function getAyahs(GetAyahsRequest $request): JsonResponse
    {
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

        return $this->success($result);
    }
}
