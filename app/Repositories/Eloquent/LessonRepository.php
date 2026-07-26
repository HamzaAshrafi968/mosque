<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\LessonRepositoryInterface;
use App\Models\Lesson;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LessonRepository extends BaseRepository implements LessonRepositoryInterface
{
    public function __construct(Lesson $model)
    {
        parent::__construct($model);
    }

    public function paginateByTeacher(string $teacherId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->with(['subject:id,name', 'classroom:id,name'])
            ->where('teacher_id', $teacherId)
            ->latest()
            ->paginate($perPage);
    }
}
