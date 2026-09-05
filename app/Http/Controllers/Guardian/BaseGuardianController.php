<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\StudentAcademicService;
use Illuminate\Http\Request;

/**
 * Shared guard logic for the guardian portal (spec §36): a guardian may only
 * open students connected through parent_students.
 */
abstract class BaseGuardianController extends Controller
{
    public function __construct(protected readonly StudentAcademicService $academic) {}

    protected function currentGuardian(Request $request): Guardian
    {
        return Guardian::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    /** Own children with their current section snapshot. */
    protected function children(Request $request)
    {
        return $this->currentGuardian($request)
            ->students()
            ->with(['classroom:id,name', 'section:id,name,classroom_id'])
            ->orderBy('name')
            ->get();
    }

    /** 403 unless the student is one of the guardian's children. */
    protected function childOrFail(Request $request, string $studentId): Student
    {
        $student = $this->currentGuardian($request)
            ->students()
            ->with(['classroom:id,name', 'section:id,name,classroom_id'])
            ->find($studentId);

        abort_if(! $student, 403, 'لا يمكنك الوصول لبيانات هذا الطالب');

        return $student;
    }
}
