<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'subjects' => Subject::with('teacher:id,name')->orderBy('name')->get(),
            'teachers' => Teacher::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $subject = Subject::create($this->validated($request));

        return response()->json([
            'message' => 'تمت إضافة المادة',
            'data' => $subject,
        ], 201);
    }

    public function update(Request $request, Subject $subject): JsonResponse
    {
        $subject->update($this->validated($request));

        return response()->json([
            'message' => 'تم تحديث المادة',
            'data' => $subject->fresh(),
        ]);
    }

    public function destroy(Subject $subject): JsonResponse
    {
        $subject->delete();

        return response()->json(['message' => 'تم حذف المادة']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'weekly_lessons' => ['required', 'integer', 'min:1', 'max:50'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
        ]);
    }
}
