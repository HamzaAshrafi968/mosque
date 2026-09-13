<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ScheduleRepositoryInterface;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;

class ScheduleRepository extends BaseRepository implements ScheduleRepositoryInterface
{
    public function __construct(Schedule $model)
    {
        parent::__construct($model);
    }

    public function getWithFilters(array $filters): Collection
    {
        return $this->model
            ->with([
                'classroom:id,name',
                'section:id,name',
                'subject:id,name',
                'teacher:id,name',
                'program:id,name,color',
                'programPeriod:id,name,starts_at,ends_at',
                'studySession:id,name',
            ])
            ->when(! empty($filters['classroom_id']), fn ($q) => $q->where('classroom_id', $filters['classroom_id']))
            ->when(! empty($filters['teacher_id']), fn ($q) => $q->where('teacher_id', $filters['teacher_id']))
            ->when(! empty($filters['program_id']), fn ($q) => $q->where('program_id', $filters['program_id']))
            ->when(! empty($filters['study_session_id']), fn ($q) => $q->where('study_session_id', $filters['study_session_id']))
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get();
    }

    public function getForTeacher(string $teacherId): Collection
    {
        return $this->model
            ->with([
                'classroom:id,name',
                'section:id,name',
                'subject:id,name',
                'program:id,name,color',
                'programPeriod:id,name,starts_at,ends_at',
                'studySession:id,name',
            ])
            ->where('teacher_id', $teacherId)
            ->orderBy('day_of_week')
            ->orderBy('starts_at')
            ->get()
            ->groupBy('day_of_week');
    }
}
