<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\SubjectRepositoryInterface;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Collection;

class SubjectRepository extends BaseRepository implements SubjectRepositoryInterface
{
    public function __construct(Subject $model)
    {
        parent::__construct($model);
    }

    public function allWithTeacher(): Collection
    {
        return $this->model->with('teacher:id,name')->orderBy('name')->get();
    }
}
