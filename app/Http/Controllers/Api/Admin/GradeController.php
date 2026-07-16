<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Grade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    public function index(Request $request): JsonResponse
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

        return response()->json(['exams' => $exams]);
    }

    public function show(Exam $exam): JsonResponse
    {
        $exam->load(['subject:id,name', 'classroom:id,name']);

        $grades = $exam->grades()
            ->with('student:id,name')
            ->orderByDesc('score')
            ->get();

        return response()->json([
            'exam' => $exam,
            'grades' => $grades,
        ]);
    }

    public function approve(Exam $exam): JsonResponse
    {
        Grade::where('exam_id', $exam->id)
            ->where('status', 'submitted')
            ->update(['status' => 'approved']);

        return response()->json(['message' => 'تم اعتماد النتائج']);
    }
}
