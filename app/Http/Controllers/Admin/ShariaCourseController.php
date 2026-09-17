<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShariaAttendanceStatus;
use App\Enums\ShariaCourseStatus;
use App\Enums\ShariaLessonType;
use App\Enums\ShariaMemorizationStatus;
use App\Http\Controllers\Controller;
use App\Models\ShariaCourse;
use App\Models\ShariaCourseLesson;
use App\Models\ShariaCourseStudent;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AuditLogger;
use App\Services\ShariaCourseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShariaCourseController extends Controller
{
    private const TABS = ['lessons', 'students', 'attendance', 'report'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ShariaCourseService $service,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->input('status');
        $search = $request->input('search');

        $courses = ShariaCourse::query()
            ->with(['supervisors' => fn ($query) => $query->withoutGlobalScope('study_session')->select('teachers.id', 'teachers.name')])
            ->withCount(['students', 'lessons'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.sharia-courses.index', [
            'courses' => $courses,
            'statuses' => ShariaCourseStatus::cases(),
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.sharia-courses.create', [
            'teachers' => $this->teachers(),
            'statuses' => ShariaCourseStatus::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $course = DB::transaction(function () use ($request) {
            $data = $this->validatedCourse($request);

            $course = ShariaCourse::create([
                ...collect($data)->except('supervisor_ids')->all(),
                'created_by' => $request->user()->id,
            ]);

            $course->supervisors()->sync($data['supervisor_ids'] ?? []);

            return $course;
        });

        $this->audit->logModel('sharia_course.created', $course, actor: $request->user());

        return redirect()
            ->route('admin.sharia-courses.show', $course)
            ->with('success', 'تم إنشاء الدورة الشرعية');
    }

    public function show(Request $request, ShariaCourse $course): View
    {
        $tab = in_array($request->input('tab'), self::TABS, true) ? $request->input('tab') : 'lessons';

        $course->load([
            'supervisors' => fn ($query) => $query->withoutGlobalScope('study_session')->select('teachers.id', 'teachers.name'),
            'students',
        ]);

        $data = [
            'course' => $course,
            'tab' => $tab,
            'teachers' => $this->teachers(),
            'lessonTypes' => ShariaLessonType::cases(),
            'attendanceStatuses' => ShariaAttendanceStatus::cases(),
            'memorizationStatuses' => ShariaMemorizationStatus::cases(),
        ];

        if ($tab === 'lessons') {
            $data['lessons'] = $course->lessons()
                ->with('teacher:id,name')
                ->orderByDesc('date')
                ->orderBy('start_time')
                ->get();
        }

        if ($tab === 'students') {
            $data['students'] = $course->students()
                ->with('student:id,name')
                ->withCount('attendances')
                ->orderBy('name')
                ->get();

            $data['availableStudents'] = Student::query()
                ->active()
                ->whereNotIn('id', $course->students()->whereNotNull('student_id')->pluck('student_id'))
                ->with('classroom:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'classroom_id']);
        }

        if ($tab === 'attendance') {
            $data += $this->attendanceTabData($request, $course);
        }

        if ($tab === 'report') {
            $data['report'] = $course->students()
                ->orderBy('name')
                ->get()
                ->map(fn (ShariaCourseStudent $student) => [
                    'student' => $student,
                    'summary' => $this->service->attendanceSummary($course, $student),
                ]);
        }

        return view('admin.sharia-courses.show', $data);
    }

    public function edit(ShariaCourse $course): View
    {
        $course->load('supervisors');

        return view('admin.sharia-courses.edit', [
            'course' => $course,
            'teachers' => $this->teachers(),
            'statuses' => ShariaCourseStatus::cases(),
        ]);
    }

    public function update(Request $request, ShariaCourse $course): RedirectResponse
    {
        $before = $course->getAttributes();
        $data = $this->validatedCourse($request);

        DB::transaction(function () use ($course, $data) {
            $course->update(collect($data)->except('supervisor_ids')->all());
            $course->supervisors()->sync($data['supervisor_ids'] ?? []);
        });

        $this->audit->logModel('sharia_course.updated', $course, $before, actor: $request->user());

        return redirect()
            ->route('admin.sharia-courses.show', $course)
            ->with('success', 'تم تحديث الدورة الشرعية');
    }

    public function destroy(Request $request, ShariaCourse $course): RedirectResponse
    {
        $this->audit->logModel('sharia_course.deleted', $course, actor: $request->user());
        $course->delete();

        return redirect()
            ->route('admin.sharia-courses.index')
            ->with('success', 'تم حذف الدورة وملحقاتها');
    }

    // ---------- الدروس والمحاضرات ----------

    public function storeLesson(Request $request, ShariaCourse $course): RedirectResponse
    {
        $data = $this->validatedLesson($request, $course);

        $lesson = $course->lessons()->create([
            'tenant_id' => $course->tenant_id,
            'title' => $data['title'],
            'type' => $data['type'],
            'date' => $data['date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
            'description' => $data['description'] ?? null,
            'attachment_path' => $request->hasFile('attachment')
                ? $request->file('attachment')->store('sharia-courses/'.$course->id, 'public')
                : null,
        ]);

        $this->audit->logModel('sharia_course.lesson_added', $lesson, actor: $request->user());

        return redirect()
            ->route('admin.sharia-courses.show', ['course' => $course, 'tab' => 'lessons'])
            ->with('success', 'تمت إضافة الدرس/المحاضرة');
    }

    public function updateLesson(Request $request, ShariaCourseLesson $lesson): RedirectResponse
    {
        $data = $this->validatedLesson($request, $lesson->course);
        $before = $lesson->getAttributes();

        $attributes = [
            'title' => $data['title'],
            'type' => $data['type'],
            'date' => $data['date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        if ($request->hasFile('attachment')) {
            if ($lesson->attachment_path) {
                Storage::disk('public')->delete($lesson->attachment_path);
            }
            $attributes['attachment_path'] = $request->file('attachment')->store('sharia-courses/'.$lesson->course_id, 'public');
        }

        $lesson->update($attributes);

        $this->audit->logModel('sharia_course.lesson_updated', $lesson, $before, actor: $request->user());

        return redirect()
            ->route('admin.sharia-courses.show', ['course' => $lesson->course_id, 'tab' => 'lessons'])
            ->with('success', 'تم تحديث الدرس/المحاضرة');
    }

    public function destroyLesson(Request $request, ShariaCourseLesson $lesson): RedirectResponse
    {
        $courseId = $lesson->course_id;

        if ($lesson->attachment_path) {
            Storage::disk('public')->delete($lesson->attachment_path);
        }

        $this->audit->logModel('sharia_course.lesson_removed', $lesson, actor: $request->user());
        $lesson->delete();

        return redirect()
            ->route('admin.sharia-courses.show', ['course' => $courseId, 'tab' => 'lessons'])
            ->with('success', 'تم حذف الدرس/المحاضرة');
    }

    // ---------- الطلاب (سجل مستقل + طلاب موجودون) ----------

    /** تسجيل طلاب موجودين في الجامع بالدورة. */
    public function storeExistingStudents(Request $request, ShariaCourse $course): RedirectResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'uuid', Rule::exists('students', 'id')->where('tenant_id', $course->tenant_id)],
        ]);

        $added = $this->service->syncEnrolledStudents($course, $data['student_ids'], $request->user());

        return redirect()
            ->route('admin.sharia-courses.show', ['course' => $course, 'tab' => 'students'])
            ->with('success', $added > 0 ? "تم تسجيل {$added} طالباً في الدورة" : 'كل الطلاب المحددين مسجَّلون مسبقاً');
    }

    /** تحديث حالة حفظ طالب في الدورة (مدير الجامع أو مشرفو الدورة). */
    public function updateMemorization(Request $request, ShariaCourseStudent $student): RedirectResponse
    {
        $data = $request->validate([
            'memorization_status' => ['nullable', Rule::enum(ShariaMemorizationStatus::class)],
            'memorization_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->updateMemorization(
            $student,
            $data['memorization_status'] !== null ? ShariaMemorizationStatus::from($data['memorization_status']) : null,
            $data['memorization_notes'] ?? null,
            $request->user(),
        );

        return redirect()
            ->route('admin.sharia-courses.show', ['course' => $student->course_id, 'tab' => 'students'])
            ->with('success', 'تم تحديث حالة الحفظ');
    }

    public function storeStudent(Request $request, ShariaCourse $course): RedirectResponse
    {
        $student = $course->students()->create([
            'tenant_id' => $course->tenant_id,
            ...$this->validatedStudent($request),
        ]);

        $this->audit->logModel('sharia_course.student_added', $student, actor: $request->user());

        return redirect()
            ->route('admin.sharia-courses.show', ['course' => $course, 'tab' => 'students'])
            ->with('success', 'تمت إضافة الطالب للدورة');
    }

    public function updateStudent(Request $request, ShariaCourseStudent $student): RedirectResponse
    {
        $before = $student->getAttributes();
        $student->update($this->validatedStudent($request));

        $this->audit->logModel('sharia_course.student_updated', $student, $before, actor: $request->user());

        return redirect()
            ->route('admin.sharia-courses.show', ['course' => $student->course_id, 'tab' => 'students'])
            ->with('success', 'تم تحديث بيانات الطالب');
    }

    public function destroyStudent(Request $request, ShariaCourseStudent $student): RedirectResponse
    {
        $courseId = $student->course_id;

        $this->audit->logModel('sharia_course.student_removed', $student, actor: $request->user());
        $student->delete();

        return redirect()
            ->route('admin.sharia-courses.show', ['course' => $courseId, 'tab' => 'students'])
            ->with('success', 'تم حذف الطالب وسجلات حضوره');
    }

    // ---------- الحضور ----------

    public function storeAttendance(Request $request, ShariaCourse $course): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'lesson_id' => ['nullable', 'uuid', Rule::exists('sharia_course_lessons', 'id')->where('course_id', $course->id)],
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'excused'])],
            'marks.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $lesson = isset($data['lesson_id']) ? ShariaCourseLesson::findOrFail($data['lesson_id']) : null;

        $this->service->saveAttendance($course, $lesson, $data['date'], $data['marks'], $request->user());

        return redirect()
            ->route('admin.sharia-courses.show', array_filter([
                'course' => $course,
                'tab' => 'attendance',
                'lesson_id' => $lesson?->id,
                'date' => $data['date'],
            ]))
            ->with('success', 'تم حفظ الحضور بنجاح');
    }

    private function attendanceTabData(Request $request, ShariaCourse $course): array
    {
        return $this->service->attendanceTabData(
            $course,
            $request->input('lesson_id'),
            $request->input('date', now()->toDateString())
        );
    }

    private function validatedCourse(Request $request): array
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'supervisor_ids' => ['nullable', 'array'],
            'supervisor_ids.*' => ['nullable', 'uuid', Rule::exists('teachers', 'id')->where('tenant_id', $tenantId)],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['draft', 'active', 'completed', 'cancelled'])],
        ]);
    }

    private function validatedLesson(Request $request, ShariaCourse $course): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['lesson', 'lecture'])],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'teacher_id' => ['nullable', 'uuid', Rule::exists('teachers', 'id')->where('tenant_id', $course->tenant_id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png'],
        ]);

        if (! empty($data['start_time']) && ! empty($data['end_time']) && $data['end_time'] <= $data['start_time']) {
            throw ValidationException::withMessages([
                'end_time' => ['يجب أن يكون وقت النهاية بعد وقت البداية'],
            ]);
        }

        return $data;
    }

    private function validatedStudent(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birth_date' => ['nullable', 'date'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function teachers()
    {
        return Teacher::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
