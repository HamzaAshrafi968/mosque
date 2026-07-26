<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Http\Requests\Api\V1\Teacher\StoreExamRequest;
use App\Http\Resources\Api\V1\ExamResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends BaseTeacherController
{
    public function index(Request $request, ExamRepositoryInterface $examRepository): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $exams = $examRepository->paginateByTeacher($teacher->id);

        return $this->success([
            'exams' => ExamResource::collection($exams),
        ]);
    }

    public function store(StoreExamRequest $request, ExamRepositoryInterface $examRepository): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $exam = $examRepository->create([
            ...$request->validated(),
            'teacher_id' => $teacher->id,
        ]);

        return $this->created(
            ExamResource::make($exam),
            'تم إنشاء الاختبار'
        );
    }
}
