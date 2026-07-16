<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index(): JsonResponse
    {
        $exams = Exam::query()
            ->with(['subject:id,name', 'classroom:id,name', 'section:id,name'])
            ->withCount('grades')
            ->latest('exam_date')
            ->paginate(20);

        return response()->json(['exams' => $exams]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'exam_date' => ['required', 'date'],
            'total_marks' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $exam = Exam::create($data);

        return response()->json([
            'message' => 'تم إنشاء الاختبار',
            'data' => $exam,
        ], 201);
    }

    public function destroy(Exam $exam): JsonResponse
    {
        $exam->delete();

        return response()->json(['message' => 'تم حذف الاختبار']);
    }
}
