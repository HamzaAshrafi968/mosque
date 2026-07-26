<?php

namespace App\Actions\Admin\Student;

use App\Contracts\Repositories\StudentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListStudentsAction
{
    public function execute(StudentRepositoryInterface $repository, array $filters): LengthAwarePaginator
    {
        return $repository->paginateWithFilters($filters);
    }
}
