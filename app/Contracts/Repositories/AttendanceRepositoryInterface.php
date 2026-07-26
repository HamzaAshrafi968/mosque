<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AttendanceRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters, int $perPage = 30): LengthAwarePaginator;

    public function getForDate(string $date, array $studentIds): Collection;

    public function upsertBatch(array $rows): void;
}
