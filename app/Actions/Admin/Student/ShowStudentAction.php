<?php

namespace App\Actions\Admin\Student;

use App\Contracts\Repositories\StudentRepositoryInterface;
use App\Models\Student;

class ShowStudentAction
{
    public function execute(StudentRepositoryInterface $repository, string $id): ?Student
    {
        $student = $repository->findWithRelations($id);

        if (! $student) {
            return null;
        }

        $attendanceSummary = $student->attendances()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $student->setAttribute('attendance_summary', $attendanceSummary);

        return $student;
    }
}
