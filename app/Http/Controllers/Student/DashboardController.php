<?php

namespace App\Http\Controllers\Student;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends BaseStudentController
{
    public function index(Request $request): View
    {
        $student = $this->currentStudent($request);

        return view('student.dashboard', [
            'student' => $student,
            'attendance' => $this->academic->attendanceSummary($student),
            'upcomingExams' => $this->academic->upcomingExams($student),
            'homeworks' => $this->academic->homeworks($student),
            'teachers' => $this->academic->teachers($student),
            'publishedGrades' => $this->academic->publishedGrades($student),
            'quran' => $this->academic->quranStats($student),
        ]);
    }
}
