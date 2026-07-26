<?php

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Collection;

interface ScheduleRepositoryInterface extends BaseRepositoryInterface
{
    public function getWithFilters(array $filters): Collection;

    public function getForTeacher(string $teacherId): Collection;
}
