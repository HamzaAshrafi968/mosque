<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

interface GradeRepositoryInterface extends BaseRepositoryInterface
{
    public function upsertBatch(array $rows): void;

    public function getByExam(string $examId): Collection;

    public function approveByExam(string $examId): void;

    public function paginateApproved(int $perPage = 100): CursorPaginator;
}
