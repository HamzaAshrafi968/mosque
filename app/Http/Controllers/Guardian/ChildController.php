<?php

namespace App\Http\Controllers\Guardian;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ChildController extends BaseGuardianController
{
    /** Overview: personal data, class/section, summaries and quick links. */
    public function overview(Request $request, string $student): View
    {
        $child = $this->childOrFail($request, $student);

        return view('guardian.children.overview', [
            'child' => $child,
            'attendance' => $this->academic->attendanceSummary($child),
            'upcomingExams' => $this->academic->upcomingExams($child),
            'homeworks' => $this->academic->homeworks($child),
            'teachers' => $this->academic->teachers($child),
        ]);
    }

    public function attendance(Request $request, string $student): View
    {
        $child = $this->childOrFail($request, $student);

        return view('guardian.children.attendance', [
            'child' => $child,
            'history' => $this->academic->attendanceHistory($child),
            'summary' => $this->academic->attendanceSummary($child),
        ]);
    }

    public function subjects(Request $request, string $student): View
    {
        $child = $this->childOrFail($request, $student);

        return view('guardian.children.subjects', [
            'child' => $child,
            'subjects' => $this->academic->subjects($child),
        ]);
    }

    public function teachers(Request $request, string $student): View
    {
        $child = $this->childOrFail($request, $student);

        return view('guardian.children.teachers', [
            'child' => $child,
            'teachers' => $this->academic->teachers($child),
        ]);
    }

    public function exams(Request $request, string $student): View
    {
        $child = $this->childOrFail($request, $student);

        return view('guardian.children.exams', [
            'child' => $child,
            'upcomingExams' => $this->academic->upcomingExams($child),
            'grades' => $this->academic->publishedGrades($child),
        ]);
    }

    public function grades(Request $request, string $student): View
    {
        $child = $this->childOrFail($request, $student);

        return view('guardian.children.grades', [
            'child' => $child,
            'grades' => $this->academic->publishedGrades($child),
        ]);
    }

    public function homeworks(Request $request, string $student): View
    {
        $child = $this->childOrFail($request, $student);

        return view('guardian.children.homeworks', [
            'child' => $child,
            'homeworks' => $this->academic->homeworks($child),
        ]);
    }

    public function announcements(Request $request, string $student): View
    {
        $child = $this->childOrFail($request, $student);

        return view('guardian.children.announcements', [
            'child' => $child,
            'announcements' => $this->academic->announcements($child, true),
        ]);
    }
}
