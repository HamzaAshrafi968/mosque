<?php

namespace App\Http\Controllers\Student;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «مراجعة 5» دُمجت في «ملفي القرآني» (الشاشة الموحدة).
 */
class KhamsaController extends BaseStudentController
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('student.quran-profile');
    }
}
