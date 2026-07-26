<?php

namespace App\Contracts\Repositories;

use App\Models\Section;
use Illuminate\Database\Eloquent\Collection;

interface ClassroomRepositoryInterface extends BaseRepositoryInterface
{
    public function allWithSectionsAndCounts(): Collection;

    public function sortedList(): Collection;

    public function createSection(string $classroomId, array $data): Section;

    public function deleteSection(string $sectionId): void;
}
