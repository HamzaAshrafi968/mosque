<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Contracts\Repositories\GradeRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Api\V1\ExamResource;
use App\Http\Resources\Api\V1\GradeResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class GradeController extends BaseApiController
{
    public function __construct(
        private readonly ExamRepositoryInterface $examRepository,
        private readonly GradeRepositoryInterface $gradeRepository,
    ) {}

    public function index(): JsonResponse
    {
        $exams = $this->examRepository->paginateWithGradeCounts();

        return $this->success([
            'exams' => ExamResource::collection($exams),
        ]);
    }

    public function show(string $examId): JsonResponse
    {
        $exam = $this->examRepository->findOrFail($examId);
        $exam->load(['subject:id,name', 'classroom:id,name']);

        $grades = $this->gradeRepository->getByExam($examId);

        return $this->success([
            'exam' => new ExamResource($exam),
            'grades' => GradeResource::collection($grades),
        ]);
    }

    public function approve(string $examId): JsonResponse
    {
        $exam = $this->examRepository->findOrFail($examId);

        $this->gradeRepository->approveByExam($examId);

        DashboardService::flush($exam->tenant_id);

        return $this->success(message: 'تم اعتماد النتائج');
    }
}
