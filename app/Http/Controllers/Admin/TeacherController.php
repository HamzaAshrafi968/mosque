<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Homework;
use App\Models\Lesson;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\TeacherCertificate;
use App\Models\TeacherRating;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CustomFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function __construct(
        private readonly CustomFieldService $customFields,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $teachers = Teacher::query()
            ->withCount('subjects')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->input('gender')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.teachers.index', ['teachers' => $teachers]);
    }

    public function create(): View
    {
        return view('admin.teachers.create', [
            'customFields' => $this->customFields->definitions(Teacher::CUSTOM_FIELD_ENTITY),
        ]);
    }

    public function show(Teacher $teacher): View
    {
        $teacher->load([
            'subjects:id,name',
            'ratings' => fn ($q) => $q->with('user:id,name')->latest(),
            'certificates' => fn ($q) => $q->latest(),
            'assignedSections.classroom:id,name',
        ]);

        $activity = [
            'lessons_count' => Lesson::where('teacher_id', $teacher->id)->count(),
            'exams_count' => Exam::where('teacher_id', $teacher->id)->count(),
            'homeworks_count' => Homework::where('teacher_id', $teacher->id)->count(),
            'schedules_count' => Schedule::where('teacher_id', $teacher->id)->count(),
            'attendance_days' => Attendance::where('teacher_id', $teacher->id)->distinct('date')->count('date'),
            'graded_students' => Grade::query()
                ->whereIn('status', ['submitted', 'approved'])
                ->whereHas('exam', fn ($q) => $q->where('teacher_id', $teacher->id))
                ->count(),
        ];

        return view('admin.teachers.show', [
            'teacher' => $teacher,
            'avgRating' => round((float) $teacher->ratings()->avg('rating'), 1),
            'activity' => $activity,
            'customValues' => $this->customFields->displayedValues(Teacher::CUSTOM_FIELD_ENTITY, $teacher->id),
            'assignedSections' => $teacher->assignedSections()->with('classroom:id,name')->orderBy('name')->get(),
        ]);
    }

    public function storeRating(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        TeacherRating::create([
            ...$data,
            'teacher_id' => $teacher->id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'تمت إضافة التقييم');
    }

    public function destroyRating(Request $request, Teacher $teacher, TeacherRating $rating): RedirectResponse
    {
        abort_unless($rating->teacher_id === $teacher->id, 404);

        $rating->delete();

        return back()->with('success', 'تم حذف التقييم');
    }

    public function storeCertificate(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'string', 'max:10'],
        ]);

        TeacherCertificate::create([...$data, 'teacher_id' => $teacher->id]);

        return back()->with('success', 'تمت إضافة الشهادة');
    }

    public function destroyCertificate(Request $request, Teacher $teacher, TeacherCertificate $certificate): RedirectResponse
    {
        abort_unless($certificate->teacher_id === $teacher->id, 404);

        $certificate->delete();

        return back()->with('success', 'تم حذف الشهادة');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $customFields = $data['custom_fields'] ?? [];
        $this->customFields->validate(Teacher::CUSTOM_FIELD_ENTITY, $customFields);

        $teacher = DB::transaction(function () use ($data, $request, $customFields) {
            $userId = null;

            if ($request->filled('password')) {
                $user = User::create([
                    'tenant_id' => $request->user()->tenant_id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => 'teacher',
                    'gender' => $data['gender'],
                    'phone' => $data['phone'] ?? null,
                ]);
                $userId = $user->id;
            }

            unset($data['password']);

            $teacher = Teacher::create([...Arr::except($data, ['custom_fields']), 'user_id' => $userId]);

            $this->customFields->save(Teacher::CUSTOM_FIELD_ENTITY, $teacher->id, $customFields);

            return $teacher;
        });

        $this->audit->logModel('teacher.created', $teacher, actor: $request->user());

        return redirect()->route('admin.teachers.index')->with('success', 'تمت إضافة المعلم بنجاح');
    }

    public function edit(Teacher $teacher): View
    {
        return view('admin.teachers.edit', [
            'teacher' => $teacher,
            'customFields' => $this->customFields->definitions(Teacher::CUSTOM_FIELD_ENTITY),
            'customValues' => $this->customFields->valuesFor(Teacher::CUSTOM_FIELD_ENTITY, $teacher->id),
        ]);
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $this->validated($request, $teacher);
        $customFields = $data['custom_fields'] ?? [];
        $this->customFields->validate(Teacher::CUSTOM_FIELD_ENTITY, $customFields);

        $before = $teacher->getAttributes();

        DB::transaction(function () use ($teacher, $data, $customFields) {
            $teacher->update(Arr::except($data, ['password', 'custom_fields']));
            $this->customFields->save(Teacher::CUSTOM_FIELD_ENTITY, $teacher->id, $customFields);
        });

        $this->audit->logModel('teacher.updated', $teacher, $before, actor: $request->user());

        return redirect()->route('admin.teachers.show', $teacher)->with('success', 'تم تحديث بيانات المعلم');
    }

    public function destroy(Request $request, Teacher $teacher): RedirectResponse
    {
        $this->audit->logModel('teacher.deleted', $teacher, actor: $request->user());
        $teacher->delete();

        return redirect()->route('admin.teachers.index')->with('success', 'تم حذف المعلم');
    }

    private function validated(Request $request, ?Teacher $teacher = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::requiredIf(fn () => $teacher === null && $request->filled('password')),
                Rule::unique('users', 'email')->ignore($teacher?->user_id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'hired_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'password' => ['nullable', 'string', 'min:8'],
            'custom_fields' => ['nullable', 'array'],
        ]);
    }
}
