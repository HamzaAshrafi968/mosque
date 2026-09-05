<?php

namespace App\Http\Controllers\Student;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PortalController extends BaseStudentController
{
    public function attendance(Request $request): View
    {
        $student = $this->currentStudent($request);

        return view('student.attendance', [
            'student' => $student,
            'history' => $this->academic->attendanceHistory($student),
            'summary' => $this->academic->attendanceSummary($student),
        ]);
    }

    public function subjects(Request $request): View
    {
        $student = $this->currentStudent($request);

        return view('student.subjects', [
            'student' => $student,
            'subjects' => $this->academic->subjects($student),
        ]);
    }

    public function teachers(Request $request): View
    {
        $student = $this->currentStudent($request);

        return view('student.teachers', [
            'student' => $student,
            'teachers' => $this->academic->teachers($student),
        ]);
    }

    public function exams(Request $request): View
    {
        $student = $this->currentStudent($request);

        return view('student.exams', [
            'student' => $student,
            'upcomingExams' => $this->academic->upcomingExams($student),
            'grades' => $this->academic->publishedGrades($student),
        ]);
    }

    public function grades(Request $request): View
    {
        $student = $this->currentStudent($request);

        return view('student.grades', [
            'student' => $student,
            'grades' => $this->academic->publishedGrades($student),
        ]);
    }

    public function homeworks(Request $request): View
    {
        $student = $this->currentStudent($request);

        return view('student.homeworks', [
            'student' => $student,
            'homeworks' => $this->academic->homeworks($student),
        ]);
    }

    /** Student submits their own homework answer (spec §18). */
    public function submitHomework(Request $request, Homework $homework): RedirectResponse
    {
        $student = $this->currentStudent($request);

        $submission = HomeworkSubmission::query()
            ->where('homework_id', $homework->id)
            ->where('student_id', $student->id)
            ->first();

        if (! $submission) {
            throw ValidationException::withMessages(['homework' => 'هذا الواجب غير موجه إليك']);
        }

        if ($submission->status === 'graded') {
            throw ValidationException::withMessages(['homework' => 'لا يمكن تعديل واجب تم تصحيحه بالفعل']);
        }

        $content = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ])['content'];

        $submission->update([
            'content' => $content,
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'تم إرسال الواجب بنجاح');
    }

    public function announcements(Request $request): View
    {
        $student = $this->currentStudent($request);

        return view('student.announcements', [
            'student' => $student,
            'announcements' => $this->academic->announcements($student),
        ]);
    }
}
