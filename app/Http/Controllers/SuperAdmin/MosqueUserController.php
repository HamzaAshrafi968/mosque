<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MosqueUserController extends Controller
{
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
        ]);
    }

    public function store(Request $request, Tenant $mosque, RoleService $roles): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_code' => ['required', 'string', 'exists:roles,code'],
            'gender' => ['required', 'in:male,female'],
            'phone' => ['nullable', 'string', 'max:30'],
            'specialty' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $mosque, $roles) {
            $role = Role::where('tenant_id', $mosque->id)->where('code', $data['role_code'])->firstOrFail();

            $isTeacherRole = $data['role_code'] === RoleService::ROLE_TEACHER || $data['role_code'] !== RoleService::ROLE_MOSQUE_MANAGER;

            $user = User::create([
                'tenant_id' => $mosque->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role_code'] === RoleService::ROLE_MOSQUE_MANAGER ? User::ROLE_ADMIN : User::ROLE_TEACHER,
                'gender' => $data['gender'],
                'phone' => $data['phone'] ?? null,
            ]);

            $roles->assignRole($user, $role->code);

            // Teacher-role users get a teacher profile row so attendance,
            // exams, lessons and schedule features work.
            if ($isTeacherRole) {
                Teacher::create([
                    'tenant_id' => $mosque->id,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'gender' => $user->gender,
                    'phone' => $user->phone,
                    'specialty' => $data['specialty'] ?? null,
                    'is_active' => true,
                ]);
            }
        });

        return redirect()->route('super-admin.mosques.users.index', $mosque)
            ->with('success', 'تم إنشاء المستخدم');
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
            $managerRole = Role::where('tenant_id', $mosque->id)->where('code', RoleService::ROLE_MOSQUE_MANAGER)->first();
            $otherManagers = User::withoutGlobalScope('tenant')
                ->where('users.tenant_id', $mosque->id)
                ->whereHas('roles', fn ($q) => $q->where('roles.id', $managerRole->id))
                ->where('users.id', '!=', $user->id)
                ->count();

            abort_unless($otherManagers > 0, 422, 'لا يمكن إزالة آخر مدير للجامع');
        }

        DB::transaction(function () use ($user, $mosque, $role, $roles, $isManager) {
            $user->update(['role' => $isManager ? User::ROLE_ADMIN : User::ROLE_TEACHER]);

            $user->roles()
                ->wherePivotIn('role_id', Role::where('tenant_id', $mosque->id)->pluck('id'))
                ->detach();

            $roles->assignRole($user, $role->code);
        });

        return back()->with('success', 'تم تحديث دور المستخدم');
    }

    public function destroy(Tenant $mosque, User $user): RedirectResponse
    {
        abort_unless($user->tenant_id === $mosque->id, 404);
        abort_unless(! $user->isSuperAdmin(), 403);

        if ($user->isAdmin()) {
            $managerRole = Role::where('tenant_id', $mosque->id)->where('code', RoleService::ROLE_MOSQUE_MANAGER)->first();
            $otherManagers = User::withoutGlobalScope('tenant')
                ->where('users.tenant_id', $mosque->id)
                ->whereHas('roles', fn ($q) => $q->where('roles.id', $managerRole->id))
                ->where('users.id', '!=', $user->id)
                ->count();

            abort_unless($otherManagers > 0, 422, 'لا يمكن حذف آخر مدير للجامع');
        }

        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            Teacher::withoutGlobalScope('tenant')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        return back()->with('success', 'تم حذف المستخدم');
    }
}
