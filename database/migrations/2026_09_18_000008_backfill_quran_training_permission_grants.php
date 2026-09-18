<?php

use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Database\Migrations\Migration;

/**
 * منح صلاحيات «برامج الاستماع» (تدريبي/إجازة/تأهيلي) للأدوار النظامية
 * القائمة: المدير (كل الصلاحيات في نطاق الجامع)، الأستاذ (كل الصلاحيات في
 * نطاقه)، والطالب (مشاهدة/تسجيل استماع لبرامجه) — بنفس نمط 2026_09_16_000006.
 */
return new class extends Migration
{
    private const MANAGER_GRANTS = [
        'quran_training.view' => 'mosque',
        'quran_training.create' => 'mosque',
        'quran_training.update' => 'mosque',
        'quran_training.listen' => 'mosque',
        'quran_training.test' => 'mosque',
    ];

    private const TEACHER_GRANTS = [
        'quran_training.view' => 'own',
        'quran_training.create' => 'own',
        'quran_training.update' => 'own',
        'quran_training.listen' => 'own',
        'quran_training.test' => 'own',
    ];

    private const STUDENT_GRANTS = [
        'quran_training.view' => 'own',
        'quran_training.listen' => 'own',
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
