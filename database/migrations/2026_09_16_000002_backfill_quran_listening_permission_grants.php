<?php

use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Database\Migrations\Migration;

/**
 * منح صلاحيات «خطة الاستماع والاختبار» للأدوار النظامية القائمة:
 * المدير (كل الصلاحيات)، الأستاذ (كل الصلاحيات في نطاقه)، والطالب
 * (مشاهدة خطته وتسجيل الاستماع) — بنفس نمط 2026_09_15_000006.
 */
return new class extends Migration
{
    private const MANAGER_GRANTS = [
        'quran_listening.view' => 'mosque',
        'quran_listening.create' => 'mosque',
        'quran_listening.update' => 'mosque',
        'quran_listening.listen' => 'mosque',
        'quran_listening.test' => 'mosque',
    ];

    private const TEACHER_GRANTS = [
        'quran_listening.view' => 'own',
        'quran_listening.create' => 'own',
        'quran_listening.update' => 'own',
        'quran_listening.listen' => 'own',
        'quran_listening.test' => 'own',
    ];

    private const STUDENT_GRANTS = [
        'quran_listening.view' => 'own',
        'quran_listening.listen' => 'own',
    ];

    public function up(): void
    {
        app(RoleService::class)->ensurePermissionCatalog();

        $this->grant(RoleService::ROLE_MOSQUE_MANAGER, self::MANAGER_GRANTS);
        $this->grant(RoleService::ROLE_TEACHER, self::TEACHER_GRANTS);
        $this->grant(RoleService::ROLE_STUDENT, self::STUDENT_GRANTS);
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
