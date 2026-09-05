<?php

namespace App\Http\Controllers\Teacher;

use App\Models\RewardPoint;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RewardPointController extends BaseTeacherController
{
    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $points = RewardPoint::query()
            ->with(['student:id,name', 'awardedBy:id,name', 'quranReviewSession:id,surah_id,from_ayah,to_ayah', 'quranReviewSession.surah:id,name_arabic'])
            ->where('awarded_by', $request->user()->id)
            ->when($request->student_id, fn ($q) => $q->where('student_id', $request->student_id))
            ->orderByDesc('created_at')
            ->paginate(20);

        $students = Student::query()->active()->orderBy('name')->get(['id', 'name']);

        return view('teacher.reward-points.index', [
            'points' => $points,
            'students' => $students,
        ]);
    }

    public function create(Request $request): View
    {
        $studentId = $request->input('student_id');

        $students = Student::query()->active()->orderBy('name')->get(['id', 'name']);

        return view('teacher.reward-points.create', [
            'students' => $students,
            'studentId' => $studentId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => [
                'required',
                'uuid',
                Rule::exists('students', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'points' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:earned,deducted'],
            'notes' => ['nullable', 'string'],
        ]);

        RewardPoint::create([
            ...$data,
            'awarded_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('teacher.reward-points.index')
            ->with('success', 'تم إضافة النقاط بنجاح');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $point = RewardPoint::findOrFail($id);

        abort_unless(
            $point->awarded_by === $request->user()->id && $point->quran_review_session_id === null,
            403
        );

        $point->delete();

        return back()->with('success', 'تم حذف سجل النقاط');
    }
}
