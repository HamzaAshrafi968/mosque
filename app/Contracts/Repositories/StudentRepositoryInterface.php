<?php

namespace App\Contracts\Repositories;

use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StudentRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function findWithRelations(string $id): ?Student;

    public function toggleArchive(string $id): Student;
}
