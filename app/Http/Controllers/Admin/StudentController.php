<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceMetricService;
use App\Services\AuditLogger;
use App\Services\CustomFieldService;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(
        private readonly CustomFieldService $customFields,
        private readonly EnrollmentService $enrollment,
        private readonly AttendanceMetricService $attendanceMetrics,
        private readonly FinanceService $finance,
        private readonly AuditLogger $audit,
    ) {}

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
            'classrooms' => $this->classroomsTree(),
            'customFields' => $this->customFields->definitions(Student::CUSTOM_FIELD_ENTITY),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $customFieldPayload = $request->input('custom_fields', []);
        $this->customFields->validate(Student::CUSTOM_FIELD_ENTITY, $customFieldPayload);

        $student = Student::create(collect($data)->except(['custom_fields', 'portal_email', 'portal_password'])->all());

        DB::transaction(function () use ($student, $customFieldPayload) {
            $this->customFields->save(Student::CUSTOM_FIELD_ENTITY, $student->id, $customFieldPayload);
        });

        try {
            $this->syncPortalAccount($student, $data, $request);
        } catch (ValidationException $e) {
            $student->delete();

            return back()->withErrors($e->errors())->withInput();
        }

        $this->enrollment->syncPlacement($student, $data['section_id'] ?? null);
        $this->audit->logModel('student.created', $student, actor: $request->user());

        return redirect()->route('admin.students.index')->with('success', 'تمت إضافة الطالب بنجاح');
    }

    public function show(Student $student): View
    {
        $student->load([
            'classroom:id,name',
            'section:id,name',
            'grades' => fn ($q) => $q->with('exam:id,title,exam_date,total_marks,subject_id', 'exam.subject:id,name')->latest(),
            'enrollments.section.classroom:id,name',
        ]);

        return view('admin.students.show', [
            'student' => $student,
            'attendanceStats' => $this->attendanceMetrics->statsForStudent($student->id),
            'customValues' => $this->customFields->displayedValues(Student::CUSTOM_FIELD_ENTITY, $student->id),
            'enrollmentHistory' => $student->enrollments()->with('section.classroom:id,name')->orderByDesc('created_at')->get(),
            'transferTargets' => Section::query()
                ->active()
                ->with('classroom:id,name')
                ->orderBy('name')
                ->get()
                ->reject(fn (Section $s) => $s->id === $student->section_id),
            'finance' => $this->finance->summary('student', $student->id),
            'transactions' => $student->financialTransactions()
                ->with(['creator:id,name', 'relatedPerson'])
                ->latest()
                ->take(10)
                ->get(),
        ]);
    }

    public function edit(Student $student): View
    {
        $values = $this->customFields->valuesFor(Student::CUSTOM_FIELD_ENTITY, $student->id);

        return view('admin.students.edit', [
            'student' => $student,
            'classrooms' => $this->classroomsTree(),
            'customFields' => $this->customFields->definitions(Student::CUSTOM_FIELD_ENTITY),
            'customValues' => $values,
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $this->validated($request);
        $customFieldPayload = $request->input('custom_fields', []);
        $this->customFields->validate(Student::CUSTOM_FIELD_ENTITY, $customFieldPayload);

        $before = $student->getAttributes();

        $student->update(collect($data)->except(['custom_fields', 'section_id', 'portal_email', 'portal_password'])->all());

        DB::transaction(function () use ($student, $customFieldPayload) {
            $this->customFields->save(Student::CUSTOM_FIELD_ENTITY, $student->id, $customFieldPayload);
        });

        try {
            $this->syncPortalAccount($student, $data, $request);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        if ($data['section_id'] ?? null) {
            $this->enrollment->syncPlacement($student, $data['section_id']);
        } elseif (! $student->section_id) {
            $this->enrollment->syncPlacement($student, null);
        }

        $this->audit->logModel('student.updated', $student, $before, actor: $request->user());

        return redirect()->route('admin.students.show', $student)->with('success', 'تم تحديث بيانات الطالب');
    }

    /** Transfer to another section (membership history preserved). */
    public function transfer(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'section_id' => ['required', 'uuid', Rule::exists('sections', 'id')],
        ]);

        try {
            $target = Section::find($data['section_id']);

            if (! $target) {
                throw ValidationException::withMessages(['section_id' => ['الشعبة غير موجودة']]);
            }

            $this->enrollment->transfer($student, $target);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.sections.show', $target)->with('success', 'تم نقل الطالب بنجاح مع حفظ تاريخ الشعب السابقة');
    }

    public function archive(Student $student, Request $request): RedirectResponse
    {
        $archiving = $student->status !== 'archived';

        DB::transaction(function () use ($student, $archiving, $request) {
            if ($archiving) {
                $this->enrollment->removeFromSection($student);
            }

            $student->update(['status' => $archiving ? 'archived' : 'active']);

            $this->audit->log('student.archived', 'student', $student->id, $student->tenant_id,
                after: ['status' => $student->status],
                actor: $request->user()
            );
        });

        return back()->with('success', 'تم تحديث حالة الطالب');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $this->audit->logModel('student.deleted', $student, actor: $request->user());

        $student->delete();

        return redirect()->route('admin.students.index')->with('success', 'تم حذف الطالب');
    }

    private function classroomsTree()
    {
        return Classroom::with('sections:id,classroom_id,name,status')->orderBy('name')->get();
    }

    private function validated(Request $request): array
    {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
            'classroom_id' => ['nullable', 'uuid', Rule::exists('classrooms', 'id')->where('tenant_id', $tenantId)],
            'section_id' => ['nullable', 'uuid', Rule::exists('sections', 'id')->where('tenant_id', $tenantId)],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'portal_email' => ['nullable', 'email', 'max:255'],
            'portal_password' => ['nullable', 'string', 'min:6', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
        ]);
    }

    /**
     * Create / update / revoke the student portal login (users.role = student).
     * Works like the teacher account flow: optional, one account per student.
     */
    private function syncPortalAccount(Student $student, array $data, Request $request): void
    {
        $email = trim($data['portal_email'] ?? '');
        $password = $data['portal_password'] ?? null;

        $user = $student->user_id ? User::find($student->user_id) : null;

        if ($email === '') {
            // Revoke: remove the account link (keep the user if reused elsewhere).
            if ($user) {
                $student->update(['user_id' => null]);

                if (! $user->teacher()->exists() && ! $user->guardian()->exists()) {
                    $user->delete();
                }
            }

            return;
        }

        if ($password === null) {
            if (! $user) {
                throw ValidationException::withMessages(['portal_password' => 'كلمة المرور مطلوبة لإنشاء حساب جديد']);
            }

            return;
        }

        $tenantId = $request->user()->tenant_id;

        if ($user) {
            $user->update([
                'name' => $student->name,
                'password' => $password,
                'email' => $email,
            ]);

            return;
        }

        $exists = User::query()->where('email', $email)->exists();

        if ($exists) {
            throw ValidationException::withMessages(['portal_email' => ['البريد مستخدم من قبل حساب آخر']]);
        }

        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $student->name,
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_STUDENT,
            'gender' => $student->gender,
        ]);

        $student->update(['user_id' => $user->id]);

        $this->audit->log('student.portal_account_created', 'student', $student->id, $student->tenant_id,
            after: ['email' => $email], actor: $request->user());
    }
}
