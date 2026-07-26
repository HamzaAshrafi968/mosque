<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Contracts\Repositories\LessonRepositoryInterface;
use App\Http\Requests\Api\V1\Teacher\StoreLessonRequest;
use App\Http\Resources\Api\V1\LessonResource;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends BaseTeacherController
{
    public function index(Request $request, LessonRepositoryInterface $lessonRepository): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $lessons = $lessonRepository->paginateByTeacher($teacher->id);

        return $this->success([
            'lessons' => LessonResource::collection($lessons),
        ]);
    }

    public function store(StoreLessonRequest $request, LessonRepositoryInterface $lessonRepository): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        $data = $request->validated();

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('lessons', 'public');
        }
        unset($data['file']);

        $lesson = $lessonRepository->create([
            ...$data,
            'teacher_id' => $teacher->id,
        ]);

        return $this->created(
            LessonResource::make($lesson),
            'تمت إضافة الدرس'
        );
    }

    public function destroy(Request $request, Lesson $lesson): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($lesson->teacher_id === $teacher->id, 403);

        $lesson->delete();

        return $this->success(message: 'تم حذف الدرس');
    }
}
