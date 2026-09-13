<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesProfilePhoto;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    use HandlesProfilePhoto;

    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,teacher'],
            'gender' => ['required', 'in:male,female'],
            'phone' => ['nullable', 'string', 'max:30'],
        ], $this->profilePhotoRules()));

        $data = array_merge($data, $this->resolveProfilePhoto($request));
        unset($data['remove_photo']);

        User::create($data);

        return back()->with('success', 'تم إنشاء المستخدم');
    }

    public function update(Request $request, User $user, RoleService $roles): RedirectResponse
    {
        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'in:admin,teacher'],
        ], $this->profilePhotoRules()));

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $data = array_merge($data, $this->resolveProfilePhoto($request, $user->photo));
        unset($data['remove_photo']);

        $roleChanged = $user->role !== $data['role'];

        if ($roleChanged && $user->isAdmin() && $data['role'] !== User::ROLE_ADMIN) {
            $this->ensureNotLastManager($user);
        }

        $user->update($data);

        // Keep the RBAC pivots in sync with the legacy users.role string.
        if ($roleChanged) {
            $user->roles()
                ->wherePivotIn('role_id', Role::where('tenant_id', $user->tenant_id)->pluck('id'))
                ->detach();

            $roles->assignRole($user, $data['role'] === User::ROLE_ADMIN
                ? RoleService::ROLE_MOSQUE_MANAGER
                : RoleService::ROLE_TEACHER);
        }

        return back()->with('success', 'تم تحديث المستخدم');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403);

        if ($user->isAdmin()) {
            $this->ensureNotLastManager($user);
        }

        $user->roles()->detach();
        $user->delete();

        return back()->with('success', 'تم حذف المستخدم');
    }

    /** Never remove the last mosque manager of a mosque. */
    private function ensureNotLastManager(User $user): void
    {
        $managerRole = Role::where('tenant_id', $user->tenant_id)
            ->where('code', RoleService::ROLE_MOSQUE_MANAGER)
            ->first();

        $otherManagers = User::withoutGlobalScope('tenant')
            ->where('users.tenant_id', $user->tenant_id)
            ->when($managerRole, fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('roles.id', $managerRole->id)))
            ->where('users.id', '!=', $user->id)
            ->count();

        abort_unless($otherManagers > 0, 422, 'لا يمكن إزالة آخر مدير للجامع');
    }
}
