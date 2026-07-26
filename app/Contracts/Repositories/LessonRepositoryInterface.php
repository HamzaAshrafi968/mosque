<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LessonRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateByTeacher(string $teacherId, int $perPage = 15): LengthAwarePaginator;
}
