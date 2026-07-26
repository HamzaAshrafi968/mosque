<?php

namespace App\Contracts\Repositories;

use App\Models\Homework;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface HomeworkRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateByTeacher(string $teacherId, int $perPage = 15): LengthAwarePaginator;

    public function createWithSubmissions(array $data): Homework;
}
