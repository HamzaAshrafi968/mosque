<?php

namespace App\Http\Controllers\Student;

use App\Models\QuranKhamsaReview;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «مراجعة 5» في بوابة الطالب: يعرض مراجعاته وخمساتها وحالتها فقط.
 */
class KhamsaController extends BaseStudentController
{
    public function index(Request $request): View
    {
        $student = $this->currentStudent($request);

        $reviews = QuranKhamsaReview::query()
            ->with([
                'teacher:id,name',
                'studySession:id,name',
                'items' => fn ($query) => $query->orderBy('juz')->orderBy('khamsa'),
            ])
            ->where('student_id', $student->id)
            ->orderByRaw("case status when 'pending' then 0 when 'completed' then 1 else 2 end")
            ->orderByDesc('assigned_at')
            ->get();

        return view('student.quran-khamsa', [
            'student' => $student,
            'reviews' => $reviews,
        ]);
    }
}
