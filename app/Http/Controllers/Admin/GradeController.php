<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Student;
use App\Services\DashboardService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GradeController extends Controller
{
    public function index(Request $request): View
    {
        $exams = Exam::query()
            ->with(['subject:id,name', 'classroom:id,name'])
            ->withCount([
                'grades',
                'grades as submitted_grades_count' => fn ($q) => $q->where('status', 'submitted'),
                'grades as approved_grades_count' => fn ($q) => $q->where('status', 'approved'),
            ])
            ->latest('exam_date')
            ->paginate(20);

        return view('admin.grades.index', ['exams' => $exams]);
    }

    public function show(Exam $exam): View
    {
        $exam->load(['subject:id,name', 'classroom:id,name']);

        $grades = $exam->grades()
            ->with('student:id,name')
            ->orderByDesc('score')
            ->get();

        return view('admin.grades.show', ['exam' => $exam, 'grades' => $grades]);
    }

    public function approve(Exam $exam): RedirectResponse
    {
        $studentIds = Grade::query()
            ->where('exam_id', $exam->id)
            ->where('status', 'submitted')
            ->pluck('student_id');

        Grade::where('exam_id', $exam->id)
            ->where('status', 'submitted')
            ->update(['status' => 'approved']);

        if ($studentIds->isNotEmpty()) {
            $roster = Student::query()
                ->whereIn('id', $studentIds)
                ->get(['id', 'name', 'tenant_id', 'user_id']);

            app(NotificationService::class)->notifyRoster(
                $roster,
                'نشر نتائج امتحان',
                "تم اعتماد ونشر نتائج امتحان «{$exam->title}» — يمكنك الاطلاع عليها من البوابة",
                route('student.grades', [], false)
            );
        }

        DashboardService::flush($exam->tenant_id);

        return back()->with('success', 'تم اعتماد النتائج');
    }
}
