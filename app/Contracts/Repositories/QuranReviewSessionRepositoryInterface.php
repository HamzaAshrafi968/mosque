<?php

namespace App\Contracts\Repositories;

use App\Models\QuranReviewSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface QuranReviewSessionRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function findWithRelations(string $id): ?QuranReviewSession;

    public function getStatistics(): array;

    public function getByStudent(string $studentId): Collection;
}
