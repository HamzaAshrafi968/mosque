<?php

use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Database\Migrations\Migration;

/**
 * منح صلاحيات «مراجعة 5» (الخمسات) وأجزاء الحفظ للأدوار النظامية القائمة،
 * بنفس نمط 2026_09_13_000001: الأدوار المخصصة تبقى دون تغيير.
 */
return new class extends Migration
{
    private const MANAGER_GRANTS = [
        'quran_khamsa.view' => 'mosque',
        'quran_khamsa.create' => 'mosque',
        'quran_khamsa.update' => 'mosque',
        'quran_khamsa.complete' => 'mosque',
        'quran.memorization.manage' => 'mosque',
    ];

    private const TEACHER_GRANTS = [
        'quran_khamsa.view' => 'own',
        'quran_khamsa.create' => 'own',
        'quran_khamsa.update' => 'own',
        'quran_khamsa.complete' => 'own',
        'quran.memorization.manage' => 'own',
    ];

    public function up(): void
    {
        app(RoleService::class)->ensurePermissionCatalog();

        $this->grant(RoleService::ROLE_MOSQUE_MANAGER, self::MANAGER_GRANTS);
        $this->grant(RoleService::ROLE_TEACHER, self::TEACHER_GRANTS);
    }

    public function down(): void
    {
        // لا يُسحب المنح عند التراجع حتى لا يُقفل المستخدمون خارج الميزة.
    }

    /** @param array<string, string> $grants code => scope */
    private function grant(string $roleCode, array $grants): void
    {
        Role::where('code', $roleCode)->each(function (Role $role) use ($grants) {
            foreach ($grants as $code => $scope) {
                $permission = Permission::where('code', $code)->first();

                if (! $permission) {
                    continue;
                }

                if (! $role->permissions()->where('permissions.code', $code)->exists()) {
                    $role->permissions()->attach($permission->id, ['scope' => $scope]);
                }
            }
        });
    }
};
