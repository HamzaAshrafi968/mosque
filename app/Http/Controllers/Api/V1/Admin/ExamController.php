<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Admin\StoreExamRequest;
use App\Http\Resources\Api\V1\ExamResource;
use Illuminate\Http\JsonResponse;

class ExamController extends BaseApiController
{
    public function __construct(
        private readonly ExamRepositoryInterface $examRepository,
    ) {}

    public function index(): JsonResponse
    {
        $exams = $this->examRepository->paginateWithRelations();

        return $this->success([
            'exams' => ExamResource::collection($exams),
        ]);
    }

    public function store(StoreExamRequest $request): JsonResponse
    {
        $exam = $this->examRepository->create($request->validated());

        return $this->created(
            ExamResource::make($exam),
            'تم إنشاء الاختبار'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->examRepository->delete($id);

        return $this->success(message: 'تم حذف الاختبار');
    }
}
