<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\HomeworkRepositoryInterface;
use App\Models\Homework;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class HomeworkRepository extends BaseRepository implements HomeworkRepositoryInterface
{
    public function __construct(Homework $model)
    {
        parent::__construct($model);
    }

    public function paginateByTeacher(string $teacherId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->with(['subject:id,name', 'classroom:id,name', 'section:id,name'])
            ->withCount([
                'submissions',
                'submissions as pending_submissions_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->where('teacher_id', $teacherId)
            ->latest('due_date')
            ->paginate($perPage);
    }

    public function createWithSubmissions(array $data): Homework
    {
        $homework = $this->create($data);

        $studentIds = Student::active()
            ->where('classroom_id', $homework->classroom_id)
            ->when($homework->section_id, fn ($q) => $q->where('section_id', $homework->section_id))
            ->pluck('id');

        $homework->submissions()->createMany(
            $studentIds->map(fn ($id) => [
                'tenant_id' => $homework->tenant_id,
                'student_id' => $id,
            ])->all()
        );

        return $homework;
    }
}
