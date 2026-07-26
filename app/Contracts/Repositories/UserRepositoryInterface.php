<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateUsers(int $perPage = 20): LengthAwarePaginator;

    public function getRecipientsExcept(string $userId): Collection;
}
