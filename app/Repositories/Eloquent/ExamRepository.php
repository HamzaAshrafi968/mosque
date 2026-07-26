<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Models\Exam;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ExamRepository extends BaseRepository implements ExamRepositoryInterface
{
    public function __construct(Exam $model)
    {
        parent::__construct($model);
    }

    public function paginateWithRelations(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['subject:id,name', 'classroom:id,name', 'section:id,name'])
            ->withCount('grades')
            ->latest('exam_date')
            ->paginate($perPage);
    }

    public function paginateWithGradeCounts(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['subject:id,name', 'classroom:id,name'])
            ->withCount([
                'grades',
                'grades as submitted_grades_count' => fn ($q) => $q->where('status', 'submitted'),
                'grades as approved_grades_count' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->latest('exam_date')
            ->paginate($perPage);
    }

    public function paginateByTeacher(string $teacherId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->with(['subject:id,name', 'classroom:id,name', 'section:id,name'])
            ->withCount('grades')
            ->where('teacher_id', $teacherId)
            ->latest('exam_date')
            ->paginate($perPage);
    }
}
