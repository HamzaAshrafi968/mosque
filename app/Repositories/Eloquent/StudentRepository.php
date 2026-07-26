<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\StudentRepositoryInterface;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StudentRepository extends BaseRepository implements StudentRepositoryInterface
{
    public function __construct(Student $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['classroom:id,name', 'section:id,name'])
            ->search($filters['q'] ?? null)
            ->when(! empty($filters['classroom_id']), fn ($q) => $q->where('classroom_id', $filters['classroom_id']))
            ->when(! empty($filters['gender']), fn ($q) => $q->where('gender', $filters['gender']))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']), fn ($q) => $q->active())
            ->latest()
            ->paginate($perPage);
    }

    public function findWithRelations(string $id): ?Student
    {
        return $this->model
            ->with([
                'classroom:id,name',
                'section:id,name',
                'grades' => fn ($q) => $q->with('exam:id,title,exam_date,total_marks,subject_id', 'exam.subject:id,name')->latest(),
            ])
            ->find($id);
    }

    public function toggleArchive(string $id): Student
    {
        $student = $this->findOrFail($id);
        $student->update(['status' => $student->status === 'archived' ? 'active' : 'archived']);

        return $student;
    }
}
