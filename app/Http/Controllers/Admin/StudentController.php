<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
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

        return view('admin.students.index', [
            'students' => $students,
            'classrooms' => Classroom::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.students.create', [
            'classrooms' => Classroom::with('sections:id,classroom_id,name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Student::create($this->validated($request));

        return redirect()->route('admin.students.index')->with('success', 'تمت إضافة الطالب بنجاح');
    }

    public function show(Student $student): View
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

        return view('admin.students.show', [
            'student' => $student,
            'attendanceSummary' => $attendanceSummary,
        ]);
    }

    public function edit(Student $student): View
    {
        return view('admin.students.edit', [
            'student' => $student,
            'classrooms' => Classroom::with('sections:id,classroom_id,name')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $student->update($this->validated($request));

        return redirect()->route('admin.students.index')->with('success', 'تم تحديث بيانات الطالب');
    }

    public function archive(Student $student): RedirectResponse
    {
        $student->update(['status' => $student->status === 'archived' ? 'active' : 'archived']);

        return back()->with('success', 'تم تحديث حالة الطالب');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()->route('admin.students.index')->with('success', 'تم حذف الطالب');
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
