<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RewardPoint;
use App\Models\Student;
use App\Models\StudySession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RewardPointController extends Controller
{
    public function index(Request $request): View
    {
        $points = RewardPoint::query()
            ->with(['student:id,name', 'awardedBy:id,name', 'studySession:id,name,gender', 'quranReviewSession:id,surah_id,from_ayah,to_ayah', 'quranReviewSession.surah:id,name_arabic'])
            ->when($request->student_id, fn ($q) => $q->where('student_id', $request->student_id))
            ->when($request->study_session_id, fn ($q) => $q->where('study_session_id', $request->study_session_id))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $students = Student::orderBy('name')->get(['id', 'name']);
        $sessions = StudySession::orderForDisplay()->get(['id', 'name', 'gender']);

        $totals = RewardPoint::query()
            ->selectRaw("
                SUM(CASE WHEN type = 'earned' THEN points ELSE 0 END) as total_earned,
                SUM(CASE WHEN type = 'deducted' THEN points ELSE 0 END) as total_deducted
            ")
            ->first();

        return view('admin.reward-points.index', [
            'points' => $points,
            'students' => $students,
            'sessions' => $sessions,
            'totalEarned' => $totals->total_earned ?? 0,
            'totalDeducted' => $totals->total_deducted ?? 0,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.reward-points.create', [
            'students' => Student::query()->active()->orderBy('name')->get(['id', 'name']),
            'studentId' => $request->input('student_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => [
                'required',
                'uuid',
                Rule::exists('students', 'id')->where('tenant_id', config('app.current_tenant_id') ?? $request->user()->tenant_id),
            ],
            'points' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:earned,deducted'],
            'notes' => ['nullable', 'string'],
        ]);

        $student = Student::query()->find($data['student_id']);

        RewardPoint::create([
            ...$data,
            'awarded_by' => $request->user()->id,
            'study_session_id' => $student?->study_session_id,
        ]);

        return redirect()
            ->route('admin.reward-points.index')
            ->with('success', 'تم إضافة النقاط بنجاح');
    }

    public function destroy(string $id): RedirectResponse
    {
        $point = RewardPoint::findOrFail($id);

        abort_if($point->isAutomatic(), 403, 'لا يمكن حذف نقاط ممنوحة تلقائياً');

        $point->delete();

        return back()->with('success', 'تم حذف سجل النقاط');
    }
}
