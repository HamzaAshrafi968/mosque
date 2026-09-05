<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SectionTeacherRole;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AttendanceMetricService;
use App\Services\AuditLogger;
use App\Services\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollment,
        private readonly AttendanceMetricService $attendanceMetrics,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        $classrooms = Classroom::query()
            ->with(['sections:id,classroom_id,name,status'])
            ->withCount(['students' => fn ($q) => $q->active()])
            ->orderBy('name')
            ->get();

        return view('admin.classrooms.index', [
            'classrooms' => $classrooms,
        ]);
    }

    public function create(): View
    {
        return view('admin.classrooms.form', ['classroom' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $classroom = Classroom::create($this->validatedClassroom($request));
        $this->audit->logModel('class.created', $classroom, actor: $request->user());

        return redirect()->route('admin.classrooms.show', $classroom)->with('success', 'تم إنشاء الصف');
    }

    /** Class page: sections, counts, actions (spec §8.1). */
    public function show(Classroom $classroom): View
    {
        $sections = $classroom->sections()
            ->withCount([
                'students' => fn ($s) => $s->active(),
                'teacherAssignments' => fn ($a) => $a->where('status', 'active'),
                'attendanceSessions',
            ])
            ->orderBy('name')
            ->get();

        return view('admin.classrooms.show', [
            'classroom' => $classroom,
            'sections' => $sections,
            'studentsCount' => $sections->sum('students_count'),
            'assignmentsCount' => $sections->sum('teacher_assignments_count'),
        ]);
    }

    public function edit(Classroom $classroom): View
    {
        return view('admin.classrooms.form', ['classroom' => $classroom]);
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $before = $classroom->getAttributes();
        $classroom->update($this->validatedClassroom($request));
        $this->audit->logModel('class.updated', $classroom, $before, actor: $request->user());

        return redirect()->route('admin.classrooms.show', $classroom)->with('success', 'تم تحديث بيانات الصف');
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        if ($classroom->sections()->whereHas('sectionStudents', fn ($q) => $q->where('status', 'active'))->exists()) {
            return back()->withErrors(['classroom' => 'لا يمكن حذف صف يحتوي شعباً بها طلاب نشطون']);
        }

        $this->audit->logModel('class.deleted', $classroom, actor: $request->user());
        $classroom->delete();

        return redirect()->route('admin.classrooms.index')->with('success', 'تم حذف الصف');
    }

    public function storeSection(Request $request, Classroom $classroom): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $section = $classroom->sections()->create([...$data, 'tenant_id' => $classroom->tenant_id]);
        $this->audit->logModel('section.created', $section, actor: $request->user());

        return redirect()->route('admin.sections.show', $section)->with('success', 'تم إنشاء الشعبة');
    }

    public function updateSection(Request $request, Section $section): RedirectResponse
    {
        $before = $section->getAttributes();
        $section->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]));

        $this->audit->logModel('section.updated', $section, $before, actor: $request->user());

        return back()->with('success', 'تم تحديث بيانات الشعبة');
    }

    /** Section dashboard: roster with percentages, teachers, actions (spec §8.2/§9). */
    public function showSection(Section $section): View
    {
        $section->load(['classroom:id,name', 'teacherAssignments.teacher:id,name,phone', 'classroom.sections:id,classroom_id,name']);

        $roster = $this->attendanceMetrics->rosterStats($section);

        $rosterStudents = Student::query()
            ->active()
            ->where('section_id', $section->id)
            ->orderBy('name')
            ->pluck('id');

        $availableStudents = Student::query()
            ->active()
            ->where(fn ($q) => $q->whereNull('section_id'))
            ->whereNotIn('id', $rosterStudents)
            ->orderBy('name')
            ->get(['id', 'name', 'guardian_name']);

        $availableTeachers = Teacher::query()
            ->where('is_active', true)
            ->whereDoesntHave('sectionAssignments', fn ($q) => $q->where('section_id', $section->id)->where('status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $sections = Section::query()
            ->active()
            ->with('classroom:id,name')
            ->orderBy('name')
            ->get();

        return view('admin.sections.show', [
            'section' => $section,
            'roster' => $roster,
            'availableStudents' => $availableStudents,
            'availableTeachers' => $availableTeachers,
            'sections' => $sections,
            'enrollments' => $section->sectionStudents()
                ->with(['student:id,name', 'section:id,name'])
                ->orderByDesc('updated_at')
                ->take(25)
                ->get(),
        ]);
    }

    public function destroySection(Section $section): RedirectResponse
    {
        if ($section->sectionStudents()->where('status', 'active')->exists()) {
            return back()->withErrors(['section' => 'لا يمكن حذف شعبة بها طلاب نشطون — انقل الطلاب أولاً']);
        }

        $this->audit->logModel('section.deleted', $section, actor: $request->user());
        $section->delete();

        return redirect()->route('admin.classrooms.show', $section->classroom)->with('success', 'تم حذف الشعبة');
    }

    /** Enroll an existing student into this section. */
    public function enrollStudent(Request $request, Section $section): RedirectResponse
    {
        $data = $request->validate(['student_id' => ['required', 'uuid']]);

        $student = Student::find($data['student_id']);

        if (! $student) {
            return back()->withErrors(['student_id' => 'الطالب غير موجود'])->withInput();
        }

        try {
            $this->enrollment->enroll($student, $section);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'تم تسجيل الطالب في الشعبة');
    }

    /** Remove a student from this section (keeps the membership history). */
    public function removeStudent(Section $section, Student $student): RedirectResponse
    {
        try {
            $this->enrollment->removeFromSection($student);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'تم إخراج الطالب من الشعبة مع حفظ سجل العضوية');
    }

    /** Assign a teacher to this section (scope for authorization). */
    public function assignTeacher(Request $request, Section $section): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'uuid'],
            'role' => ['nullable', 'in:lead,assistant'],
        ]);

        $teacher = Teacher::find($data['teacher_id']);

        if (! $teacher) {
            return back()->withErrors(['teacher_id' => 'المعلم غير موجود'])->withInput();
        }

        try {
            $this->enrollment->assignTeacher($section, $teacher, SectionTeacherRole::tryFrom($data['role'] ?? 'lead') ?? SectionTeacherRole::Lead);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'تم تكليف المعلم بالشعبة');
    }

    public function removeTeacher(Section $section, Teacher $teacher): RedirectResponse
    {
        $this->enrollment->removeTeacher($section, $teacher);

        return back()->with('success', 'تم إنهاء تكليف المعلم بالشعبة');
    }

    private function validatedClassroom(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
