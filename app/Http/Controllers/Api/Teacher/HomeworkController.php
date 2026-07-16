<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeworkController extends BaseTeacherController
{
    public function index(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $homeworks = Homework::query()
            ->with(['subject:id,name', 'classroom:id,name', 'section:id,name'])
            ->withCount([
                'submissions',
                'submissions as pending_submissions_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->where('teacher_id', $teacher->id)
            ->latest('due_date')
            ->paginate(15);

        return response()->json(['homeworks' => $homeworks]);
    }

    public function store(Request $request): JsonResponse
    {
        $teacher = $this->currentTeacher($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('homeworks', 'public');
        }

        unset($data['attachment']);

        $homework = Homework::create([...$data, 'teacher_id' => $teacher->id]);

        $studentIds = Student::query()
            ->active()
            ->where('classroom_id', $homework->classroom_id)
            ->when($homework->section_id, fn ($q) => $q->where('section_id', $homework->section_id))
            ->pluck('id');

        $homework->submissions()->createMany(
            $studentIds->map(fn ($id) => [
                'tenant_id' => $homework->tenant_id,
                'student_id' => $id,
            ])->all()
        );

        return response()->json([
            'message' => 'تم إنشاء الواجب',
            'data' => $homework,
        ], 201);
    }

    public function submissions(Request $request, Homework $homework): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($homework->teacher_id === $teacher->id, 403);

        $homework->load(['subject:id,name', 'classroom:id,name']);

        $submissions = $homework->submissions()
            ->with('student:id,name')
            ->get()
            ->sortBy(fn ($s) => $s->student->name ?? '');

        return response()->json([
            'homework' => $homework,
            'submissions' => $submissions->values(),
        ]);
    }

    public function updateSubmission(Request $request, HomeworkSubmission $submission): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($submission->homework()->value('teacher_id') === $teacher->id, 403);

        $data = $request->validate([
            'grade' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'feedback' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,graded'],
        ]);

        $submission->update([...$data, 'submitted_at' => $submission->submitted_at ?? now()]);

        return response()->json([
            'message' => 'تم حفظ التصحيح',
            'data' => $submission->fresh(),
        ]);
    }

    public function destroy(Request $request, Homework $homework): JsonResponse
    {
        $teacher = $this->currentTeacher($request);
        abort_unless($homework->teacher_id === $teacher->id, 403);

        $homework->delete();

        return response()->json(['message' => 'تم حذف الواجب']);
    }
}
