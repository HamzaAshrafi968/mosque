<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $students = Student::query()
            ->with(['classroom:id,name', 'section:id,name'])
            ->search($request->string('q')->toString())
            ->when($request->filled('classroom_id'), fn ($q) => $q->where('classroom_id', $request->input('classroom_id')))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->input('gender')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')), fn ($q) => $q->active())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return response()->json([
            'students' => $students,
            'classrooms' => Classroom::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $student = Student::create($this->validated($request));

        return response()->json([
            'message' => 'تمت إضافة الطالب بنجاح',
            'data' => $student,
        ], 201);
    }

    public function show(Student $student): JsonResponse
    {
        $student->load([
            'classroom:id,name',
            'section:id,name',
            'grades' => fn ($q) => $q->with('exam:id,title,exam_date,total_marks,subject_id', 'exam.subject:id,name')->latest(),
        ]);

        $attendanceSummary = $student->attendances()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'student' => $student,
            'attendance_summary' => $attendanceSummary,
        ]);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $student->update($this->validated($request));

        return response()->json([
            'message' => 'تم تحديث بيانات الطالب',
            'data' => $student->fresh(),
        ]);
    }

    public function archive(Student $student): JsonResponse
    {
        $student->update(['status' => $student->status === 'archived' ? 'active' : 'archived']);

        return response()->json(['message' => 'تم تحديث حالة الطالب']);
    }

    public function destroy(Student $student): JsonResponse
    {
        $student->delete();

        return response()->json(['message' => 'تم حذف الطالب']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
