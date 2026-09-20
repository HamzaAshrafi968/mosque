<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ParentStudentRelationship;
use App\Http\Controllers\Concerns\HandlesProfilePhoto;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\QuranReviewSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\User;
use App\Services\AttendanceMetricService;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\CustomFieldService;
use App\Services\EnrollmentService;
use App\Services\QuranBatchPanelService;
use App\Services\QuranKhamsaService;
use App\Support\QuranMemorizationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentController extends Controller
{
    use HandlesProfilePhoto;

    public function __construct(
        private readonly CustomFieldService $customFields,
        private readonly EnrollmentService $enrollment,
        private readonly AttendanceMetricService $attendanceMetrics,
        private readonly AuthorizationService $authorization,
        private readonly AuditLogger $audit,
        private readonly QuranKhamsaService $khamsa,
        private readonly QuranBatchPanelService $batchPanel,
    ) {}

    public function index(Request $request): View
    {
        $students = Student::query()
            ->with(['classroom:id,name', 'section:id,name', 'studySession:id,name,gender', 'guardians:id,name,phone'])
            ->search($request->string('q')->toString())
            ->when($request->filled('classroom_id'), fn ($q) => $q->where('classroom_id', $request->input('classroom_id')))
            ->when($request->filled('gender'), fn ($q) => $q->where('gender', $request->input('gender')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')), fn ($q) => $q->active())
            ->orderByStudySession()
            ->paginate(20)
            ->withQueryString();

        return view('admin.students.index', [
            'students' => $students,
            'classrooms' => Classroom::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.students.create', [
            'classrooms' => $this->classroomsTree(),
            'customFields' => $this->customFields->definitions(Student::CUSTOM_FIELD_ENTITY),
            'sessions' => StudySession::orderForDisplay()->get(),
            'selectedGuardians' => $this->guardiansForSelection($request, null),
            'canCreateGuardian' => $this->authorization->can($request->user(), 'parents.create'),
        ]);
    }

    /** AJAX student lookup for the guardian form "إضافة ابن" picker. */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $exclude = array_values(array_filter((array) $request->query('exclude', []), 'is_string'));

        if ($term === '') {
            return response()->json(['results' => []]);
        }

        $students = Student::query()
            ->active()
            ->with('classroom:id,name')
            ->where('name', 'like', '%'.$term.'%')
            ->when($exclude !== [], fn ($query) => $query->whereNotIn('id', $exclude))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'classroom_id']);

        return response()->json([
            'results' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->name,
                'meta' => $student->classroom?->name,
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $customFieldPayload = $request->input('custom_fields', []);
        $this->customFields->validate(Student::CUSTOM_FIELD_ENTITY, $customFieldPayload);

        $data = $this->applyAvatar($data, $request);

        $student = Student::create(collect($data)->except(['custom_fields', 'portal_email', 'portal_password', 'guardian_ids', 'guardian_ids_present', 'memorized_juz_numbers', 'memorized_juz_numbers_present'])->all());

        DB::transaction(function () use ($student, $customFieldPayload) {
            $this->customFields->save(Student::CUSTOM_FIELD_ENTITY, $student->id, $customFieldPayload);
        });

        try {
            $this->syncPortalAccount($student, $data, $request);
        } catch (ValidationException $e) {
            $student->delete();

            return back()->withErrors($e->errors())->withInput();
        }

        if ($student->user_id && ($data['photo'] ?? null)) {
            $student->user()->update(['photo' => $data['photo']]);
        }

        $this->syncGuardians($student, $data['guardian_ids'] ?? []);
        $this->enrollment->syncPlacement($student, $data['section_id'] ?? null);
        $this->syncMemorizedJuz($request, $student, $data);
        $this->audit->logModel('student.created', $student, actor: $request->user());

        return redirect()->route('admin.students.index')->with('success', 'تمت إضافة الطالب بنجاح');
    }

    public function show(Request $request, Student $student): View
    {
        $student->load([
            'classroom:id,name',
            'section:id,name',
            'studySession:id,name,gender',
            'guardians:id,name,phone',
            'memorizedFromSurah:id,name_arabic',
            'memorizedToSurah:id,name_arabic',
            'grades' => fn ($q) => $q->with('exam:id,title,exam_date,total_marks,subject_id', 'exam.subject:id,name')->latest(),
            'enrollments.section.classroom:id,name',
        ]);

        $cycle = $this->authorization->can($request->user(), 'quran_batch.view')
            ? $this->batchPanel->forStudent(
                $student,
                $request->user(),
                reviewShowUrl: fn (QuranReviewSession $session) => route('admin.quran-review.show', $session->id),
            )
            : [];

        return view('admin.students.show', array_merge($cycle, [
            'student' => $student,
            'cycle' => $cycle,
            'attendanceStats' => $this->attendanceMetrics->statsForStudent($student->id),
            'customValues' => $this->customFields->displayedValues(Student::CUSTOM_FIELD_ENTITY, $student->id),
            'enrollmentHistory' => $student->enrollments()->with('section.classroom:id,name')->orderByDesc('created_at')->get(),
            'transferTargets' => Section::query()
                ->active()
                ->with('classroom:id,name')
                ->orderBy('name')
                ->get()
                ->reject(fn (Section $s) => $s->id === $student->section_id),
        ]));
    }

    public function edit(Request $request, Student $student): View
    {
        $values = $this->customFields->valuesFor(Student::CUSTOM_FIELD_ENTITY, $student->id);
        $student->loadMissing('guardians:id,name,phone');

        return view('admin.students.edit', [
            'student' => $student,
            'classrooms' => $this->classroomsTree(),
            'customFields' => $this->customFields->definitions(Student::CUSTOM_FIELD_ENTITY),
            'customValues' => $values,
            'sessions' => StudySession::orderForDisplay()->get(),
            'memorizedJuz' => $this->khamsa->memorizedJuzNumbers($student),
            'selectedGuardians' => $this->guardiansForSelection($request, $student),
            'canCreateGuardian' => $this->authorization->can($request->user(), 'parents.create'),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $this->validated($request);
        $customFieldPayload = $request->input('custom_fields', []);
        $this->customFields->validate(Student::CUSTOM_FIELD_ENTITY, $customFieldPayload);

        $before = $student->getAttributes();

        $data = $this->applyAvatar($data, $request, $student->photo);

        $student->update(collect($data)->except(['custom_fields', 'section_id', 'portal_email', 'portal_password', 'portal_account_present', 'guardian_ids', 'guardian_ids_present', 'memorized_juz_numbers', 'memorized_juz_numbers_present'])->all());

        if ($student->user_id && array_key_exists('photo', $data)) {
            $student->user()->update(['photo' => $data['photo']]);
        }

        DB::transaction(function () use ($student, $customFieldPayload) {
            $this->customFields->save(Student::CUSTOM_FIELD_ENTITY, $student->id, $customFieldPayload);
        });

        // The portal account changes only when the manager explicitly enables
        // the account section; editing profile data never touches it.
        if ($request->boolean('portal_account_present')) {
            try {
                $this->syncPortalAccount($student, $data, $request);
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }
        }

        if ($request->boolean('guardian_ids_present')) {
            $this->syncGuardians($student, $data['guardian_ids'] ?? []);
        }

        if ($data['section_id'] ?? null) {
            $this->enrollment->syncPlacement($student, $data['section_id']);
        } elseif (! $student->section_id) {
            $this->enrollment->syncPlacement($student, null);
        }

        $this->syncMemorizedJuz($request, $student, $data, $before);
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

    /**
     * Guardians shown as selected chips: the student's current links, or the
     * submitted selection when a validation error sent the form back.
     */
    private function guardiansForSelection(Request $request, ?Student $student)
    {
        $oldIds = $request->old('guardian_ids');

        if (is_array($oldIds)) {
            $ids = array_values(array_filter($oldIds, 'is_string'));

            return Guardian::query()
                ->whereIn('id', $ids)
                ->get(['id', 'name', 'phone'])
                ->sortBy(fn (Guardian $guardian) => array_search($guardian->id, $ids, true))
                ->values();
        }

        return $student ? $student->guardians : collect();
    }

    /** Reconcile the student's guardian links with the submitted selection. */
    private function syncGuardians(Student $student, array $guardianIds): void
    {
        $validIds = Guardian::query()->whereIn('id', $guardianIds)->pluck('id')->all();
        $selected = array_values(array_intersect($guardianIds, $validIds));

        $links = $student->guardianLinks();

        if ($selected === []) {
            $links->delete();
        } else {
            $links->whereNotIn('parent_id', $selected)->delete();
        }

        foreach ($selected as $index => $guardianId) {
            $link = $student->guardianLinks()->firstOrNew(['parent_id' => $guardianId]);

            if ($link->exists) {
                continue;
            }

            $link->fill([
                'tenant_id' => $student->tenant_id,
                'relationship' => ParentStudentRelationship::Guardian,
                'is_primary' => $index === 0,
            ])->save();
        }
    }

    private function validated(Request $request): array
    {
        $tenantId = config('app.current_tenant_id');

        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'birth_date' => ['nullable', 'date'],
            'study_session_id' => ['nullable', 'uuid', Rule::exists('study_sessions', 'id')->where('tenant_id', $tenantId)],
            'classroom_id' => ['nullable', 'uuid', Rule::exists('classrooms', 'id')->where('tenant_id', $tenantId)],
            'section_id' => ['nullable', 'uuid', Rule::exists('sections', 'id')->where('tenant_id', $tenantId)],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_ids' => ['nullable', 'array'],
            'guardian_ids.*' => ['uuid', Rule::exists('parents', 'id')->where('tenant_id', $tenantId)],
            'guardian_ids_present' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'portal_email' => ['nullable', 'email', 'max:255'],
            'portal_password' => ['nullable', 'string', 'min:6', 'max:255'],
            'portal_account_present' => ['nullable', 'boolean'],
            'custom_fields' => ['nullable', 'array'],
            'memorized_juz_numbers' => ['nullable', 'array'],
            'memorized_juz_numbers.*' => ['integer', 'min:1', 'max:30'],
            'memorized_juz_numbers_present' => ['nullable', 'boolean'],
        ], QuranMemorizationRules::rules($request), $this->profilePhotoRules()));

        // الدوام المخصص لجنس لا يقبل طالباً من جنس آخر — نفس شرط الشعبة.
        $sessionGender = $this->genderOfSession($data['study_session_id'] ?? null);

        if ($sessionGender !== null && $sessionGender !== $data['gender']) {
            throw ValidationException::withMessages([
                'study_session_id' => ['الدوام المختار مخصص لجنس آخر — اختر دواماً يوافق جنس الطالب'],
            ]);
        }

        if (! empty($data['section_id'])) {
            $section = Section::query()->with('classroom:id,study_session_id')->find($data['section_id']);
            $sectionGender = $this->genderOfSession(
                $section?->study_session_id ?? $section?->classroom?->study_session_id
            );

            if ($sectionGender !== null && $sectionGender !== $data['gender']) {
                throw ValidationException::withMessages([
                    'section_id' => ['الشعبة المختارة في دوام '.StudySession::GENDERS[$sectionGender].' — اختر شعبة توافق جنس الطالب'],
                ]);
            }
        }

        return $data;
    }

    /** جنس الدوام (null = غير مخصص/غير موجود). */
    private function genderOfSession(?string $sessionId): ?string
    {
        if ($sessionId === null) {
            return null;
        }

        return StudySession::query()->whereKey($sessionId)->value('gender');
    }

    /**
     * مزامنة الأجزاء المحفوظة: عند إرسال شبكة الأجزاء من نموذج الطالب تكون
     * هي جهة الحقيقة، وإلا تُعبّأ تلقائياً من مقدار الحفظ (memorized_juz).
     */
    private function syncMemorizedJuz(Request $request, Student $student, array $data, array $before = []): void
    {
        $submitted = $request->boolean('memorized_juz_numbers_present')
            || array_key_exists('memorized_juz_numbers', $data);

        if ($submitted) {
            $this->khamsa->syncMemorizedJuz($student, $data['memorized_juz_numbers'] ?? [], $request->user());

            return;
        }

        if ((float) ($data['memorized_juz'] ?? 0) !== (float) ($before['memorized_juz'] ?? 0)) {
            $this->khamsa->syncIntakeMemorization($student, $request->user());
        }
    }

    /** Merge the resolved avatar (new file / removal) into the payload. */
    private function applyAvatar(array $data, Request $request, ?string $current = null): array
    {
        unset($data['photo'], $data['remove_photo']);

        return array_merge($data, $this->resolveProfilePhoto($request, $current));
    }

    /**
     * Create / update / revoke the student portal login (users.role = student).
     * Works like the teacher account flow: optional, one account per student.
     */
    private function syncPortalAccount(Student $student, array $data, Request $request): void
    {
        // Partial updates (API, photo-only saves) must not touch the portal account.
        if (! array_key_exists('portal_email', $data) && ! array_key_exists('portal_password', $data)) {
            return;
        }

        $email = trim($data['portal_email'] ?? '');
        $password = $data['portal_password'] ?? null;

        $user = $student->user_id ? User::find($student->user_id) : null;

        if ($email === '') {
            if ($user) {
                // Revoke: remove the account link (keep the user if reused elsewhere).
                $student->update(['user_id' => null]);

                if (! $user->teacher()->exists() && ! $user->guardian()->exists()) {
                    $user->delete();
                }
            } elseif ($password !== null) {
                throw ValidationException::withMessages(['portal_email' => 'البريد الإلكتروني مطلوب لإنشاء حساب جديد']);
            }

            return;
        }

        $tenantId = $request->user()->tenant_id;

        if ($user) {
            // Email and password are both optional on edit: each can change alone.
            $payload = ['name' => $student->name, 'email' => $email];

            if ($password !== null) {
                $payload['password'] = $password;
            }

            if ($email !== $user->email) {
                $exists = User::query()->where('email', $email)->where('id', '!=', $user->id)->exists();

                if ($exists) {
                    throw ValidationException::withMessages(['portal_email' => ['البريد مستخدم من قبل حساب آخر']]);
                }
            }

            $user->update($payload);

            return;
        }

        if ($password === null) {
            throw ValidationException::withMessages(['portal_password' => 'كلمة المرور مطلوبة لإنشاء حساب جديد']);
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
