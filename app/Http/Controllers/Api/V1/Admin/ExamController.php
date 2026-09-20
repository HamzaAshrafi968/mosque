<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\ManagesExamApi;
use App\Http\Requests\Api\V1\Admin\StoreExamRequest;
use App\Http\Resources\Api\V1\ExamResource;
use App\Models\Exam;
use App\Services\ExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends BaseApiController
{
    use ManagesExamApi;

    public function __construct(
        private readonly ExamRepositoryInterface $examRepository,
    ) {}

    protected function assertApiExamAccess(Request $request, Exam $exam): void
    {
        // مدير الجامع يصل لكل امتحانات جامعته (العزل العام مطبَّق مسبقاً).
    }

    public function index(): JsonResponse
    {
        $exams = $this->examRepository->paginateWithRelations();

        return $this->success([
            'exams' => ExamResource::collection($exams),
        ]);
    }

    public function store(StoreExamRequest $request, ExamService $service): JsonResponse
    {
        $exam = $service->create($request->validated());

        return $this->created(
            ExamResource::make($exam->load(['subject', 'classroom', 'section', 'classrooms', 'studySession'])),
            'تم إنشاء الاختبار'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->examRepository->delete($id);

        return $this->success(message: 'تم حذف الاختبار');
    }
}
