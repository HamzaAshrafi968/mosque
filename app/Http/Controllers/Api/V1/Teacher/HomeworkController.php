<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Contracts\Repositories\HomeworkRepositoryInterface;
use App\Http\Requests\Api\V1\Teacher\StoreHomeworkRequest;
use App\Http\Requests\Api\V1\Teacher\UpdateSubmissionRequest;
use App\Http\Resources\Api\V1\HomeworkResource;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeworkController extends BaseTeacherController
{
    public function index(Request $request, HomeworkRepositoryInterface $homeworkRepository): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $homeworks = $homeworkRepository->paginateByTeacher($teacher->id);

        return $this->success([
            'homeworks' => HomeworkResource::collection($homeworks),
        ]);
    }

    public function store(StoreHomeworkRequest $request, HomeworkRepositoryInterface $homeworkRepository): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        $data = $request->validated();

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('homeworks', 'public');
        }
        unset($data['attachment']);

        $data['teacher_id'] = $teacher->id;
        $homework = $homeworkRepository->createWithSubmissions($data);

        return $this->created(
            HomeworkResource::make($homework),
            'تم إنشاء الواجب'
        );
    }

    public function submissions(Request $request, Homework $homework): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($homework->teacher_id === $teacher->id, 403);

        $homework->load(['subject:id,name', 'classroom:id,name']);

        $submissions = $homework->submissions()
            ->with('student:id,name')
            ->get()
            ->sortBy(fn ($s) => $s->student->name ?? '');

        return $this->success([
            'homework' => new HomeworkResource($homework),
            'submissions' => $submissions->values(),
        ]);
    }

    public function updateSubmission(UpdateSubmissionRequest $request, HomeworkSubmission $submission): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($submission->homework()->value('teacher_id') === $teacher->id, 403);

        $data = $request->validated();
        $submission->update([...$data, 'submitted_at' => $submission->submitted_at ?? now()]);

        return $this->success(
            $submission->fresh(),
            'تم حفظ التصحيح'
        );
    }

    public function destroy(Request $request, Homework $homework): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($homework->teacher_id === $teacher->id, 403);

        $homework->delete();

        return $this->success(message: 'تم حذف الواجب');
    }
}
