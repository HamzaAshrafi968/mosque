<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MosqueController extends Controller
{
    public function index(): View
    {
        $mosques = Tenant::query()
            ->withCount('users')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('super-admin.mosques.index', ['mosques' => $mosques]);
    }

    public function create(): View
    {
        return view('super-admin.mosques.form', ['mosque' => null]);
    }

    public function store(Request $request, RoleService $roles): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $roles) {
            $mosque = Tenant::create([
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => 'active',
                'is_active' => true,
            ]);

            $roles->provisionTenantRoles($mosque);

            if (! empty($data['manager_name']) && ! empty($data['manager_email'])) {
                $manager = User::create([
                    'tenant_id' => $mosque->id,
                    'name' => $data['manager_name'],
                    'email' => $data['manager_email'],
                    'password' => $data['manager_password'],
                    'role' => User::ROLE_ADMIN,
                    'gender' => $data['manager_gender'] ?? 'male',
                    'phone' => $data['manager_phone'] ?? null,
                ]);

                $roles->assignRole($manager, RoleService::ROLE_MOSQUE_MANAGER);
            }
        });

        return redirect()->route('super-admin.mosques.index')->with('success', 'تم إنشاء الجامع بنجاح');
    }

    public function edit(Tenant $mosque): View
    {
        return view('super-admin.mosques.form', ['mosque' => $mosque]);
    }

    public function update(Request $request, Tenant $mosque): RedirectResponse
    {
        $mosque->update($this->validated($request));

        return redirect()->route('super-admin.mosques.index')->with('success', 'تم تحديث بيانات الجامع');
    }

    public function destroy(Tenant $mosque): RedirectResponse
    {
        $mosque->update(['status' => Tenant::STATUS_ARCHIVED, 'is_active' => false]);

        return redirect()->route('super-admin.mosques.index')->with('success', 'تمت أرشفة الجامع');
    }

    /** Switch into the mosque to operate its panel as its manager. */
    public function enter(Tenant $mosque): RedirectResponse
    {
        session(['super_admin_mosque_id' => $mosque->id]);

        return redirect()->route('admin.dashboard')->with('success', "تم الدخول إلى {$mosque->name}");
    }

    /** Leave mosque context and return to the central dashboard. */
    public function exit(): RedirectResponse
    {
        session()->forget('super_admin_mosque_id');

        return redirect()->route('super-admin.dashboard')->with('success', 'تم العودة إلى إدارة الجوامع');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('tenants', 'code')->ignore($request->route('mosque'))],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive,archived'],
            'manager_name' => ['nullable', 'required_with:manager_email', 'string', 'max:255'],
            'manager_email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'manager_password' => ['nullable', 'required_with:manager_email', 'string', 'min:8'],
            'manager_phone' => ['nullable', 'string', 'max:30'],
            'manager_gender' => ['nullable', 'in:male,female'],
        ]);
    }
}
