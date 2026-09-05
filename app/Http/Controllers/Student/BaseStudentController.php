<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentAcademicService;
use Illuminate\Http\Request;

/**
 * Shared guard for the student portal (spec §36): the student record is always
 * resolved from the authenticated user — ids can never address another student.
 */
abstract class BaseStudentController extends Controller
{
    public function __construct(protected readonly StudentAcademicService $academic) {}

    protected function currentStudent(Request $request): Student
    {
        $student = Student::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('user_id', $request->user()->id)
            ->active()
            ->with(['classroom:id,name', 'section:id,name,classroom_id'])
            ->first();

        abort_if(! $student, 403, 'لا يوجد ملف طالب مرتبط بحسابك');

        return $student;
    }
}
