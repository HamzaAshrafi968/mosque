<?php

namespace App\Http\Controllers\Student;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends BaseStudentController
{
    public function show(Request $request): View
    {
        return view('student.profile', [
            'student' => $this->currentStudent($request),
            'user' => $request->user(),
        ]);
    }
}
