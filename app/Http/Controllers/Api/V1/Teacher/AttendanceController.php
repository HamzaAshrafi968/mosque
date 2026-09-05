<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Actions\Teacher\Attendance\SaveAttendanceAction;
use App\Http\Requests\Api\V1\Teacher\AttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends BaseTeacherController
{
    /**
     * Sections assigned to the authenticated teacher (scope enforcement).
     */
    public function sections(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $sections = Section::query()
            ->with('classroom:id,name')
            ->active()
            ->whereIn('id', $teacher->manageableSectionIds())
            ->orderBy('name')
            ->get(['id', 'name', 'classroom_id', 'status']);

        return $this->success(['sections' => $sections]);
    }

    /** Roster for the chosen section/date with the already-saved statuses. */
    public function students(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        $allowedIds = $teacher->manageableSectionIds();

        $sectionId = $request->input('section_id');
        $date = $request->input('date', now()->toDateString());

        $section = $sectionId ? Section::find($sectionId) : null;

        if ($sectionId && ! $section) {
            return $this->notFound('الشعبة غير موجودة');
        }

        if ($section && ! in_array($section->id, $allowedIds, true)) {
            return $this->forbidden('لا تملك صلاحية الوصول لهذه الشعبة');
        }

        $students = Student::query()
            ->active()
            ->whereIn('section_id', $sectionId ? [$section->id] : $allowedIds)
            ->orderBy('name')
            ->get(['id', 'name', 'gender']);

        $existing = AttendanceRecord::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->whereHas('session', fn ($q) => $q->whereDate('date', $date))
            ->pluck('status', 'student_id');

        $sessionId = null;
        if ($section) {
            $sessionId = AttendanceSession::query()
                ->where('section_id', $section->id)
                ->whereDate('date', $date)
                ->value('id');
        }

        return $this->success([
            'students' => $students,
            'existing' => $existing,
            'section_id' => $section?->id,
            'date' => $date,
            'session_id' => $sessionId,
        ]);
    }

    public function store(AttendanceRequest $request, SaveAttendanceAction $action): JsonResponse
    {
        $action->execute($request->validated(), $request->user());

        return $this->success(message: 'تم حفظ الحضور بنجاح');
    }
}
