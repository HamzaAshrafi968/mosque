<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\AttendanceRepositoryInterface;
use App\Models\Attendance;

class AttendanceRepository extends BaseRepository implements AttendanceRepositoryInterface
{
    public function __construct(Attendance $model)
    {
        parent::__construct($model);
    }

    public function upsertTeacher(array $rows): void
    {
        Attendance::upsert(
            $rows,
            ['tenant_id', 'teacher_id', 'date'],
            ['status', 'notes', 'recorded_by', 'updated_at']
        );
    }
}
