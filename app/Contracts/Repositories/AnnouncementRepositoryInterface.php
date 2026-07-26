<?php

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface AnnouncementRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithAuthor(int $perPage = 15): LengthAwarePaginator;

    public function latest(int $limit = 5): Collection;
}
