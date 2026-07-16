<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends BaseTeacherController
{
    public function index(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $exams = Exam::query()
            ->with(['subject:id,name', 'classroom:id,name', 'section:id,name'])
            ->withCount('grades')
            ->where('teacher_id', $teacher->id)
            ->latest('exam_date')
            ->paginate(15);

        return response()->json(['exams' => $exams]);
    }

    public function store(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'exam_date' => ['required', 'date'],
            'total_marks' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $exam = Exam::create([...$data, 'teacher_id' => $teacher->id]);

        return response()->json([
            'message' => 'تم إنشاء الاختبار',
            'data' => $exam,
        ], 201);
    }
}
