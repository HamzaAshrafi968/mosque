<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MosqueRoleController extends Controller
{
    public function index(Tenant $mosque): View
    {
        $roles = Role::where('tenant_id', $mosque->id)
            ->withCount('users')
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        return view('super-admin.roles.index', [
            'mosque' => $mosque,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request, Tenant $mosque): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $base = Str::slug($data['name'], '_');
        $code = $base;

        while (Role::where('tenant_id', $mosque->id)->where('code', $code)->exists()) {
            $code = $base.'_'.Str::lower(Str::random(4));
        }

        Role::create([
            'tenant_id' => $mosque->id,
            'code' => $code,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        return back()->with('success', 'تم إنشاء الدور. فعّل صلاحياته من صفحة التعديل.');
    }

    public function edit(Tenant $mosque, Role $role): View
    {
        abort_unless($role->tenant_id === $mosque->id && ! $role->isGlobal(), 404);

        $granted = $role->permissions()->pluck('permission_role.scope', 'permissions.code');

        return view('super-admin.roles.matrix', [
            'mosque' => $mosque,
            'role' => $role,
            'granted' => $granted,
        ]);
    }

    public function updatePermissions(Request $request, Tenant $mosque, Role $role, RoleService $roles): RedirectResponse
    {
        abort_unless($role->tenant_id === $mosque->id && ! $role->isGlobal(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['in:global,mosque,class,section,own,'],
        ]);

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $scopes = array_filter(
            $data['permissions'] ?? [],
            fn ($scope) => in_array($scope, ['global', 'mosque', 'class', 'section', 'own'], true)
        );

        $roles->syncRolePermissions($role, $scopes);

        return back()->with('success', 'تم حفظ الصلاحيات');
    }

    public function destroy(Tenant $mosque, Role $role): RedirectResponse
    {
        abort_unless($role->tenant_id === $mosque->id && ! $role->is_system, 404);

        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        return back()->with('success', 'تم حذف الدور');
    }
}
