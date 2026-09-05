<?php

namespace App\Http\Controllers\Guardian;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends BaseGuardianController
{
    public function index(Request $request): View
    {
        $children = $this->children($request);

        $cards = $children->map(function (Student $student) {
            return [
                'student' => $student,
                'attendance' => $this->academic->attendanceSummary($student),
                'upcomingExams' => $this->academic->upcomingExams($student),
                'pendingHomeworks' => $this->academic->homeworks($student)
                    ->filter(fn ($row) => $row['submission'] && $row['submission']->status === 'pending')
                    ->count(),
                'publishedGrades' => $this->academic->publishedGrades($student)->count(),
            ];
        });

        return view('guardian.dashboard', [
            'cards' => $cards,
        ]);
    }
}
