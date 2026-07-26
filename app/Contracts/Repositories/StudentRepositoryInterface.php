<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface StudentRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function findWithRelations(string $id): ?\App\Models\Student;

    public function toggleArchive(string $id): \App\Models\Student;
}
