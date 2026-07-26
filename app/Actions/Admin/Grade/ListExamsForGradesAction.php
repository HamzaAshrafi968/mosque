<?php

namespace App\Actions\Admin\Grade;

use App\Contracts\Repositories\ExamRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListExamsForGradesAction
{
    public function execute(ExamRepositoryInterface $repository): LengthAwarePaginator
    {
        return $repository->paginateWithGradeCounts();
    }
}
