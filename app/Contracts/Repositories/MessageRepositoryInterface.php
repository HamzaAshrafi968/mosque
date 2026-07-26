<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MessageRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateByUser(string $userId, int $perPage = 20): LengthAwarePaginator;

    public function markRead(string $userId): void;
}
