<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends BaseTeacherController
{
    public function index(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $lessons = Lesson::query()
            ->with(['subject:id,name', 'classroom:id,name'])
            ->where('teacher_id', $teacher->id)
            ->latest()
            ->paginate(15);

        return response()->json(['lessons' => $lessons]);
    }

    public function store(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'type' => ['required', 'in:file,video,link,presentation'],
            'file' => ['nullable', 'file', 'max:20480', 'required_if:type,file,presentation'],
            'url' => ['nullable', 'url', 'required_if:type,video,link'],
        ]);

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('lessons', 'public');
        }

        unset($data['file']);

        $lesson = Lesson::create([...$data, 'teacher_id' => $teacher->id]);

        return response()->json([
            'message' => 'تمت إضافة الدرس',
            'data' => $lesson,
        ], 201);
    }

    public function destroy(Request $request, Lesson $lesson): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($lesson->teacher_id === $teacher->id, 403);

        $lesson->delete();

        return response()->json(['message' => 'تم حذف الدرس']);
    }
}
