<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ParentStudentRelationship;
use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\ParentStudent;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Guardian (ولي أمر) management (spec §2). A guardian profile may have its own
 * portal account and is connected to one or more students through
 * parent_students; the guardian's scope is exactly those links.
 */
class ParentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $guardians = Guardian::query()
            ->with(['user:id,name,email,role', 'students:id,name,classroom_id', 'students.classroom:id,name'])
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')), fn ($q) => $q->active())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.parents.index', [
            'guardians' => $guardians,
        ]);
    }

    public function create(): View
    {
        return view('admin.parents.form', [
            'guardian' => null,
            'students' => $this->studentsForPicker(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $guardian = DB::transaction(function () use ($data, $request) {
                $guardian = $this->createGuardian($data, $request);

                return $guardian;
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $this->audit->logModel('guardian.created', $guardian, actor: $request->user());

        return redirect()->route('admin.parents.edit', $guardian)->with('success', 'تمت إضافة ولي الأمر وربطه بالطلاب');
    }

    public function edit(Guardian $guardian): View
    {
        $guardian->load(['students:id,name,classroom_id', 'students.classroom:id,name']);

        return view('admin.parents.form', [
            'guardian' => $guardian,
            'students' => $this->studentsForPicker(),
        ]);
    }

    public function update(Request $request, Guardian $guardian): RedirectResponse
    {
        $data = $this->validated($request);
        $before = $guardian->getAttributes();

        try {
            DB::transaction(function () use ($data, $request, $guardian, $before) {
                $guardian->update([
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'status' => $data['status'] ?? 'active',
                ]);

                $this->syncAccount($guardian, $data, $request);
                $this->syncLinks($guardian, $data['student_ids'] ?? [], $data['relationships'] ?? []);
                $this->audit->logModel('guardian.updated', $guardian, $before, actor: $request->user());
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'تم تحديث بيانات ولي الأمر');
    }

    public function destroy(Request $request, Guardian $guardian): RedirectResponse
    {
        DB::transaction(function () use ($guardian, $request) {
            $this->audit->log('guardian.deleted', 'guardian', $guardian->id, $guardian->tenant_id, after: $guardian->getAttributes(), actor: $request->user());

            $user = $guardian->user;

            $guardian->links()->delete();
            $guardian->delete();

            // Clean up the portal account unless it is reused elsewhere.
            if ($user && ! $user->guardian()->exists() && ! $user->teacher()->exists() && ! $user->student()->exists()) {
                $user->delete();
            }
        });

        return redirect()->route('admin.parents.index')->with('success', 'تم حذف ولي الأمر');
    }

    private function createGuardian(array $data, Request $request): Guardian
    {
        $guardian = Guardian::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        $this->syncAccount($guardian, $data, $request);
        $this->syncLinks($guardian, $data['student_ids'] ?? [], $data['relationships'] ?? []);

        return $guardian;
    }

    /** Portal account creation/update (email + password) for the guardian. */
    private function syncAccount(Guardian $guardian, array $data, Request $request): void
    {
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? null;

        if ($email === '') {
            return;
        }

        $tenantId = $request->user()->tenant_id;

        if ($guardian->user_id) {
            if ($password) {
                $guardian->user->update(['password' => $password]);
            }

            if ($guardian->user->email !== $email) {
                $exists = User::query()->where('email', $email)->where('id', '!=', $guardian->user_id)->exists();

                if ($exists) {
                    throw ValidationException::withMessages(['email' => ['البريد مستخدم من قبل حساب آخر']]);
                }

                $guardian->user->update(['email' => $email]);
            }

            return;
        }

        $exists = User::query()->where('email', $email)->exists();

        if ($exists) {
            throw ValidationException::withMessages(['email' => ['البريد مستخدم من قبل حساب آخر']]);
        }

        if (! $password) {
            return;
        }

        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_GUARDIAN,
            'phone' => $data['phone'] ?? null,
        ]);

        $guardian->update(['user_id' => $user->id]);
    }

    /** Reconcile parent_students links (spec §2). */
    private function syncLinks(Guardian $guardian, array $studentIds, array $relationships): void
    {
        $tenantId = $guardian->tenant_id;
        $validStudents = Student::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $studentIds)
            ->pluck('id', 'id');

        $existing = $guardian->links()->pluck('student_id')->all();

        foreach (array_diff($existing, $validStudents->all()) as $removeId) {
            $guardian->links()->where('student_id', $removeId)->delete();
        }

        $primary = true;

        foreach ($validStudents as $studentId) {
            $relationship = ParentStudentRelationship::tryFrom($relationships[$studentId] ?? '') ?? ParentStudentRelationship::Guardian;

            ParentStudent::updateOrCreate(
                ['tenant_id' => $tenantId, 'parent_id' => $guardian->id, 'student_id' => $studentId],
                [
                    'relationship' => $relationship,
                    'is_primary' => $primary,
                ]
            );

            $primary = false;
        }
    }

    private function studentsForPicker()
    {
        return Student::query()
            ->active()
            ->with('classroom:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'classroom_id']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'password' => ['nullable', 'string', 'min:6'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['uuid'],
            'relationships' => ['nullable', 'array'],
            'relationships.*' => ['nullable', Rule::in(['father', 'mother', 'guardian', 'other'])],
        ]);
    }
}
