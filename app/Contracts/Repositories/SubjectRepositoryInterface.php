<?php

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Collection;

interface SubjectRepositoryInterface extends BaseRepositoryInterface
{
    public function allWithTeacher(): Collection;
}
