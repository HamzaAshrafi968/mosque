<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\AttendanceRepositoryInterface;
use App\Models\Attendance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AttendanceRepository extends BaseRepository implements AttendanceRepositoryInterface
{
    public function __construct(Attendance $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters, int $perPage = 30): LengthAwarePaginator
    {
        $type = $filters['type'] ?? 'students';

        return $this->model
            ->with(['student:id,name,classroom_id', 'student.classroom:id,name', 'teacher:id,name'])
            ->whereDate('date', $filters['date'] ?? now()->toDateString())
            ->when($type === 'students', fn ($q) => $q->whereNotNull('student_id'), fn ($q) => $q->whereNotNull('teacher_id'))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->paginate($perPage);
    }

    public function getForDate(string $date, array $studentIds): Collection
    {
        return $this->model
            ->whereDate('date', $date)
            ->whereIn('student_id', $studentIds)
            ->pluck('status', 'student_id');
    }

    public function upsertBatch(array $rows): void
    {
        Attendance::upsert(
            $rows,
            ['tenant_id', 'student_id', 'date'],
            ['status', 'recorded_by', 'updated_at']
        );
    }
}
