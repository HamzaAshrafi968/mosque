<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\TeacherRepositoryInterface;
use App\Models\Teacher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TeacherRepository extends BaseRepository implements TeacherRepositoryInterface
{
    public function __construct(Teacher $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->withCount('subjects')
            ->when(! empty($filters['q']), fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%'))
            ->when(! empty($filters['gender']), fn ($q) => $q->where('gender', $filters['gender']))
            ->latest()
            ->paginate($perPage);
    }

    public function activeTeachers(): Collection
    {
        return $this->model->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function findByUserId(string $userId): ?Teacher
    {
        return $this->model->where('user_id', $userId)->first();
    }
}
