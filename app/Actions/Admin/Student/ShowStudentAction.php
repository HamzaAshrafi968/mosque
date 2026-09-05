<?php

namespace App\Actions\Admin\Student;

use App\Contracts\Repositories\StudentRepositoryInterface;
use App\Models\Student;
use App\Services\AttendanceMetricService;

class ShowStudentAction
{
    public function __construct(private readonly AttendanceMetricService $metrics) {}

    public function execute(StudentRepositoryInterface $repository, string $id): ?Student
    {
        $student = $repository->findWithRelations($id);

        if (! $student) {
            return null;
        }

        $student->setAttribute('attendance_stats', $this->metrics->statsForStudent($id));

        return $student;
    }
}
