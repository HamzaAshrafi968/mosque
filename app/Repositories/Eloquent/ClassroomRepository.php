<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ClassroomRepositoryInterface;
use App\Models\Classroom;
use App\Models\Section;
use Illuminate\Database\Eloquent\Collection;

class ClassroomRepository extends BaseRepository implements ClassroomRepositoryInterface
{
    public function __construct(Classroom $model)
    {
        parent::__construct($model);
    }

    public function allWithSectionsAndCounts(): Collection
    {
        return $this->model
            ->with('sections:id,classroom_id,name')
            ->withCount('students')
            ->orderBy('name')
            ->get();
    }

    public function sortedList(): Collection
    {
        return $this->model->orderBy('name')->get(['id', 'name']);
    }

    public function createSection(string $classroomId, array $data): Section
    {
        $classroom = $this->findOrFail($classroomId);

        return $classroom->sections()->create($data);
    }

    public function deleteSection(string $sectionId): void
    {
        Section::findOrFail($sectionId)->delete();
    }
}
