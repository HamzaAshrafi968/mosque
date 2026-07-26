<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Actions\Teacher\Attendance\SaveAttendanceAction;
use App\Contracts\Repositories\AttendanceRepositoryInterface;
use App\Http\Requests\Api\V1\Teacher\AttendanceRequest;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends BaseTeacherController
{
    public function students(Request $request, AttendanceRepositoryInterface $attendanceRepository): JsonResponse
    {
        $classroomId = $request->input('classroom_id');
        $sectionId = $request->input('section_id');
        $date = $request->input('date', now()->toDateString());

        $students = collect();
        $existing = collect();

        if ($classroomId) {
            $students = Student::query()
                ->active()
                ->where('classroom_id', $classroomId)
                ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
                ->orderBy('name')
                ->get(['id', 'name', 'gender']);

            $existing = $attendanceRepository->getForDate($date, $students->pluck('id')->all());
        }

        return $this->success([
            'students' => $students,
            'existing' => $existing,
            'classroom_id' => $classroomId,
            'section_id' => $sectionId,
            'date' => $date,
        ]);
    }

    public function store(AttendanceRequest $request, SaveAttendanceAction $action, AttendanceRepositoryInterface $attendanceRepository): JsonResponse
    {
        $action->execute($attendanceRepository, $request->validated(), $request);

        return $this->success(message: 'تم حفظ الحضور بنجاح');
    }
}
