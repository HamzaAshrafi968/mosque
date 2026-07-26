<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ExamRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithRelations(int $perPage = 20): LengthAwarePaginator;

    public function paginateWithGradeCounts(int $perPage = 20): LengthAwarePaginator;

    public function paginateByTeacher(string $teacherId, int $perPage = 15): LengthAwarePaginator;
}
