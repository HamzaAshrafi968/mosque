<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ShariaAttendanceStatus;
use App\Enums\ShariaLessonType;
use App\Enums\ShariaMemorizationStatus;
use App\Models\ShariaCourse;
use App\Models\ShariaCourseLesson;
use App\Models\ShariaCourseStudent;
use App\Services\ShariaCourseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShariaCourseController extends BaseTeacherController
{
    private const TABS = ['lessons', 'students', 'attendance', 'report'];

    public function __construct(private readonly ShariaCourseService $service) {}

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $courses = $this->service->coursesFor($teacher)
            ->with(['supervisors' => fn ($query) => $query->withoutGlobalScope('study_session')->select('teachers.id', 'teachers.name')])
            ->withCount(['students', 'lessons'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('teacher.sharia-courses.index', ['courses' => $courses]);
    }

    public function show(Request $request, ShariaCourse $course): View
    {
        $teacher = $this->currentTeacher($request);
        $this->service->assertCourseAccess($teacher, $course);

        $tab = in_array($request->input('tab'), self::TABS, true) ? $request->input('tab') : 'lessons';

        $course->load(['supervisors' => fn ($query) => $query->withoutGlobalScope('study_session')->select('teachers.id', 'teachers.name')]);

        $data = [
            'course' => $course,
            'tab' => $tab,
            'lessonTypes' => ShariaLessonType::cases(),
            'attendanceStatuses' => ShariaAttendanceStatus::cases(),
            'memorizationStatuses' => ShariaMemorizationStatus::cases(),
            'isSupervisor' => $course->supervisors->contains('id', $teacher->id),
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
                ->withCount('attendances')
                ->orderBy('name')
                ->get();
        }

        if ($tab === 'attendance') {
            $data += $this->service->attendanceTabData(
                $course,
                $request->input('lesson_id'),
                $request->input('date', now()->toDateString())
            );
        }

        if ($tab === 'report') {
            $data['report'] = $course->students()
                ->orderBy('name')
                ->get()
                ->map(fn ($student) => [
                    'student' => $student,
                    'summary' => $this->service->attendanceSummary($course, $student),
                ]);
        }

        return view('teacher.sharia-courses.show', $data);
    }

    /** تحديث حالة حفظ طالب — مشرفو الدورة فقط. */
    public function updateMemorization(Request $request, ShariaCourseStudent $student): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $course = $student->course;

        $this->service->assertCourseAccess($teacher, $course);

        abort_unless(
            $course->supervisors()->whereKey($teacher->id)->exists(),
            403,
            'تحديث حالة الحفظ متاح لمشرفي الدورة فقط'
        );

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
            ->route('teacher.sharia-courses.show', ['course' => $course, 'tab' => 'students'])
            ->with('success', 'تم تحديث حالة الحفظ');
    }

    public function storeAttendance(Request $request, ShariaCourse $course): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);
        $this->service->assertCourseAccess($teacher, $course);

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
            ->route('teacher.sharia-courses.show', array_filter([
                'course' => $course,
                'tab' => 'attendance',
                'lesson_id' => $lesson?->id,
                'date' => $data['date'],
            ]))
            ->with('success', 'تم حفظ الحضور بنجاح');
    }
}
