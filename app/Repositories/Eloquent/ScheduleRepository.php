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
        // الدوام المطلوب صراحةً يتقدّم على دوام الترويسة النشط حتى لا يتعارض
        // الفلتران فيعود الجدول فارغاً (نفس سلوك صفحة الويب).
        $sessionId = $filters['study_session_id'] ?? null;

        if (blank($sessionId)) {
            $sessionId = config('app.current_study_session_id');
        }

        return $this->model
            ->withoutGlobalScope('study_session')
            ->notExpired()
            ->with([
                'classroom:id,name',
                'section:id,name',
                'subject:id,name',
                'teacher:id,name',
                'program:id,name,color',
                'programPeriod:id,name,starts_at,ends_at',
                'studySession:id,name,gender',
            ])
            ->when(! empty($filters['classroom_id']), fn ($q) => $q->where('classroom_id', $filters['classroom_id']))
            ->when(! empty($filters['teacher_id']), fn ($q) => $q->where('teacher_id', $filters['teacher_id']))
            ->when(! empty($filters['program_id']), fn ($q) => $q->where('program_id', $filters['program_id']))
            ->when(! blank($sessionId), fn ($q) => $q->where('study_session_id', $sessionId))
            ->orderByStudySession()
            ->get();
    }

    public function getForTeacher(string $teacherId): Collection
    {
        return $this->model
            ->activeOn(now())
            ->with([
                'classroom:id,name',
                'section:id,name',
                'subject:id,name',
                'program:id,name,color',
                'programPeriod:id,name,starts_at,ends_at',
                'studySession:id,name,gender',
            ])
            ->where('teacher_id', $teacherId)
            ->orderByStudySession()
            ->get()
            ->groupBy('day_of_week');
    }
}
