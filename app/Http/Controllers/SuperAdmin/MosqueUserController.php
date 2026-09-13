<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Concerns\HandlesProfilePhoto;
use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudySession;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MosqueUserController extends Controller
{
    use HandlesProfilePhoto;

    public function index(Tenant $mosque): View
    {
        $users = User::withoutGlobalScope('tenant')
            ->where('users.tenant_id', $mosque->id)
            ->with('roles:id,code,name,tenant_id')
            ->orderBy('name')
            ->paginate(20);

        $roles = Role::where('tenant_id', $mosque->id)->orderBy('name')->get();

        return view('super-admin.users.index', [
            'mosque' => $mosque,
            'users' => $users,
            'roles' => $roles,
            'studySessions' => StudySession::withoutGlobalScope('tenant')
                ->where('tenant_id', $mosque->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Tenant $mosque, RoleService $roles): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_code' => ['required', 'string', 'exists:roles,code'],
            'gender' => ['required', 'in:male,female'],
            'phone' => ['nullable', 'string', 'max:30'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'study_session_id' => ['nullable', 'uuid', Rule::exists('study_sessions', 'id')->where('tenant_id', $mosque->id)],
        ], $this->profilePhotoRules()));

        $data['photo'] = $this->resolveProfilePhoto($request)['photo'] ?? null;

        DB::transaction(function () use ($data, $mosque, $roles) {
            $role = Role::where('tenant_id', $mosque->id)->where('code', $data['role_code'])->firstOrFail();

            $user = User::create([
                'tenant_id' => $mosque->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $this->legacyRoleFor($data['role_code']),
                'gender' => $data['gender'],
                'phone' => $data['phone'] ?? null,
                'photo' => $data['photo'] ?? null,
            ]);

            // The created hook attaches a default role from the legacy
            // users.role string; keep only the role explicitly requested here.
            $user->roles()->detach();

            $roles->assignRole($user, $role->code);

            // كل دور يحصل على ملفه المرتبط: أستاذ/طاقم، ولي أمر، أو طالب.
            $this->syncProfiles($mosque, $user, $data['role_code'], $data, $data['photo'] ? ['photo' => $data['photo']] : []);
        });

        return redirect()->route('super-admin.mosques.users.index', $mosque)
            ->with('success', 'تم إنشاء المستخدم');
    }

    /** نموذج المستخدم الكامل: بياناته ودوره وخصائصه + مصفوفة صلاحياته الفردية. */
    public function edit(Tenant $mosque, User $user): View
    {
        $this->ensureEditableUser($mosque, $user);

        return view('super-admin.users.edit', array_merge([
            'mosque' => $mosque,
            'user' => $user,
            'roles' => Role::where('tenant_id', $mosque->id)->orderBy('name')->get(),
            'teacher' => Teacher::withoutGlobalScope('tenant')->where('user_id', $user->id)->first(),
            'studySessions' => StudySession::withoutGlobalScope('tenant')
                ->where('tenant_id', $mosque->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ], $this->matrixData($user)));
    }

    /** حفظ بيانات المستخدم ودوره ومصفوفة صلاحياته الفردية معاً. */
    public function update(Request $request, Tenant $mosque, User $user, RoleService $roles): RedirectResponse
    {
        $this->ensureEditableUser($mosque, $user);

        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role_code' => ['required', 'string'],
            'gender' => ['required', 'in:male,female'],
            'phone' => ['nullable', 'string', 'max:30'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'study_session_id' => ['nullable', 'uuid', Rule::exists('study_sessions', 'id')->where('tenant_id', $mosque->id)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['nullable', 'in:inherit,deny,mosque,class,section,own'],
        ], $this->profilePhotoRules()));

        $role = Role::where('tenant_id', $mosque->id)->where('code', $data['role_code'])->firstOrFail();
        $isManager = $data['role_code'] === RoleService::ROLE_MOSQUE_MANAGER;

        // Never drop the last active mosque manager.
        if (! $isManager && $user->isAdmin()) {
            $this->assertNotLastManager($mosque, $user);
        }

        $photo = $this->resolveProfilePhoto($request, $user->photo);

        DB::transaction(function () use ($data, $mosque, $user, $role, $roles, $photo) {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'gender' => $data['gender'],
                'phone' => $data['phone'] ?? null,
                'role' => $this->legacyRoleFor($data['role_code']),
            ];

            if (! empty($data['password'])) {
                $payload['password'] = $data['password'];
            }

            if ($photo !== []) {
                $payload = array_merge($payload, $photo);
            }

            $user->update($payload);

            // Keep only the requested mosque role on the user.
            $user->roles()
                ->wherePivotIn('role_id', Role::where('tenant_id', $mosque->id)->pluck('id'))
                ->detach();

            $roles->assignRole($user, $role->code);

            $this->syncProfiles($mosque, $user, $data['role_code'], $data, $photo);
        });

        $overrides = array_filter(
            $data['permissions'] ?? [],
            fn ($value) => in_array($value, ['deny', 'mosque', 'class', 'section', 'own'], true)
        );

        $roles->syncUserPermissions($user, $overrides);

        return redirect()
            ->route('super-admin.mosques.users.index', $mosque)
            ->with('success', 'تم تحديث المستخدم وصلاحياته');
    }

    public function updateRole(Request $request, Tenant $mosque, User $user, RoleService $roles): RedirectResponse
    {
        abort_unless($user->tenant_id === $mosque->id, 404);
        abort_unless(! $user->isSuperAdmin(), 403);

        $data = $request->validate([
            'role_code' => ['required', 'string'],
        ]);

        $role = Role::where('tenant_id', $mosque->id)->where('code', $data['role_code'])->firstOrFail();

        $isManager = $data['role_code'] === RoleService::ROLE_MOSQUE_MANAGER;

        // Never drop the last active mosque manager.
        if (! $isManager && $user->isAdmin()) {
            $this->assertNotLastManager($mosque, $user);
        }

        DB::transaction(function () use ($user, $mosque, $role, $roles) {
            $user->update(['role' => $this->legacyRoleFor($role->code)]);

            $user->roles()
                ->wherePivotIn('role_id', Role::where('tenant_id', $mosque->id)->pluck('id'))
                ->detach();

            $roles->assignRole($user, $role->code);

            $this->syncProfiles($mosque, $user, $role->code, [], []);
        });

        return back()->with('success', 'تم تحديث دور المستخدم');
    }

    public function permissions(Tenant $mosque, User $user): View
    {
        $this->ensureEditableUser($mosque, $user);

        return view('super-admin.users.permissions', array_merge([
            'mosque' => $mosque,
            'user' => $user,
        ], $this->matrixData($user)));
    }

    public function updatePermissions(Request $request, Tenant $mosque, User $user, RoleService $roles): RedirectResponse
    {
        $this->ensureEditableUser($mosque, $user);

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['nullable', 'in:inherit,deny,mosque,class,section,own'],
        ]);

        $overrides = array_filter(
            $data['permissions'] ?? [],
            fn ($value) => in_array($value, ['deny', 'mosque', 'class', 'section', 'own'], true)
        );

        $roles->syncUserPermissions($user, $overrides);

        return back()->with('success', 'تم حفظ صلاحيات المستخدم');
    }

    public function destroy(Tenant $mosque, User $user): RedirectResponse
    {
        abort_unless($user->tenant_id === $mosque->id, 404);
        abort_unless(! $user->isSuperAdmin(), 403);

        if ($user->isAdmin()) {
            $this->assertNotLastManager($mosque, $user);
        }

        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            Teacher::withoutGlobalScope('tenant')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return back()->with('success', 'تم حذف المستخدم');
    }

    /**
     * مصفوفة الصلاحيات الفردية: نطاقات الدور الأساسية + التجاوزات المباشرة.
     *
     * @return array{roleScopes: Collection, overrides: Collection}
     */
    private function matrixData(User $user): array
    {
        $roleScopes = $user->roles()
            ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->get(['permissions.code as code', 'permission_role.scope as scope'])
            ->groupBy('code')
            ->map(fn ($rows) => $rows->pluck('scope')->unique()->values()->all());

        $overrides = $user->permissions()->get()->mapWithKeys(fn (Permission $permission) => [
            $permission->code => $permission->pivot->effect === 'deny' ? 'deny' : $permission->pivot->scope,
        ]);

        return ['roleScopes' => $roleScopes, 'overrides' => $overrides];
    }

    /** نوع الدور القديم (users.role) المقابل لدور الجامع. */
    private function legacyRoleFor(string $roleCode): string
    {
        return match ($roleCode) {
            RoleService::ROLE_MOSQUE_MANAGER => User::ROLE_ADMIN,
            RoleService::ROLE_GUARDIAN => User::ROLE_GUARDIAN,
            RoleService::ROLE_STUDENT => User::ROLE_STUDENT,
            default => User::ROLE_TEACHER,
        };
    }

    /** هل الدور يملك ملف أستاذ؟ (المعلم وأي دور مخصص غير المدير/البوابات) */
    private function roleHasTeacherProfile(string $roleCode): bool
    {
        return ! in_array($roleCode, [
            RoleService::ROLE_MOSQUE_MANAGER,
            RoleService::ROLE_GUARDIAN,
            RoleService::ROLE_STUDENT,
        ], true);
    }

    /**
     * مزامنة الملف المرتبط بالدور: أستاذ/طاقم، ولي أمر، أو طالب — حتى تعمل
     * الجداول والحضور وبوابات الطالب/ولي الأمر مباشرة بعد إنشاء الحساب.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>  $photo
     */
    private function syncProfiles(Tenant $mosque, User $user, string $roleCode, array $data, array $photo): void
    {
        if ($this->roleHasTeacherProfile($roleCode)) {
            $this->syncTeacherProfile($mosque, $user, $data, $photo);

            return;
        }

        if ($roleCode === RoleService::ROLE_GUARDIAN) {
            $this->syncGuardianProfile($mosque, $user);

            return;
        }

        if ($roleCode === RoleService::ROLE_STUDENT) {
            $this->syncStudentProfile($mosque, $user, $photo);
        }
    }

    /**
     * مزامنة ملف الأستاذ مع بيانات المستخدم: يُنشأ إن لم يوجد، وتُحدَّث
     * خصائصه ودوامه من النموذج.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>  $photo
     */
    private function syncTeacherProfile(Tenant $mosque, User $user, array $data, array $photo): void
    {
        $teacher = Teacher::withoutGlobalScope('tenant')->where('user_id', $user->id)->first();

        $payload = [
            'name' => $user->name,
            'gender' => $user->gender,
            'phone' => $user->phone,
        ];

        if (array_key_exists('specialty', $data)) {
            $payload['specialty'] = $data['specialty'] ?? null;
        }

        if (array_key_exists('study_session_id', $data)) {
            $payload['study_session_id'] = $data['study_session_id'] ?? null;
        }

        if ($photo !== []) {
            $payload = array_merge($payload, $photo);
        }

        if ($teacher) {
            $teacher->update($payload);

            return;
        }

        Teacher::create($payload + [
            'tenant_id' => $mosque->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    /** ملف ولي الأمر (بوابة ولي الأمر) المرتبط بحساب المستخدم. */
    private function syncGuardianProfile(Tenant $mosque, User $user): void
    {
        $guardian = Guardian::withoutGlobalScope('tenant')->where('user_id', $user->id)->first();

        $payload = [
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
        ];

        if ($guardian) {
            $guardian->update($payload);

            return;
        }

        Guardian::create($payload + [
            'tenant_id' => $mosque->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    /**
     * ملف الطالب (بوابة الطالب) المرتبط بحساب المستخدم.
     *
     * @param  array<string, string|null>  $photo
     */
    private function syncStudentProfile(Tenant $mosque, User $user, array $photo): void
    {
        $student = Student::withoutGlobalScopes(['tenant', 'study_session'])
            ->where('user_id', $user->id)
            ->first();

        $payload = [
            'name' => $user->name,
            'gender' => $user->gender,
        ];

        if ($photo !== []) {
            $payload = array_merge($payload, $photo);
        }

        if ($student) {
            $student->update($payload);

            return;
        }

        Student::create($payload + [
            'tenant_id' => $mosque->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    private function assertNotLastManager(Tenant $mosque, User $user): void
    {
        $managerRole = Role::where('tenant_id', $mosque->id)->where('code', RoleService::ROLE_MOSQUE_MANAGER)->first();

        $otherManagers = User::withoutGlobalScope('tenant')
            ->where('users.tenant_id', $mosque->id)
            ->whereHas('roles', fn ($q) => $q->where('roles.id', $managerRole->id))
            ->where('users.id', '!=', $user->id)
            ->count();

        abort_unless($otherManagers > 0, 422, 'لا يمكن إزالة آخر مدير للجامع');
    }

    private function ensureEditableUser(Tenant $mosque, User $user): void
    {
        abort_unless($user->tenant_id === $mosque->id, 404);
        abort_if($user->isSuperAdmin(), 403);
    }
}
