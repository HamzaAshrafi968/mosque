<?php

use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Database\Migrations\Migration;

/**
 * منح صلاحيات «دفعات الحفظ» و«إعدادات برنامج القرآن» للأدوار النظامية
 * القائمة: المدير (كل الصلاحيات)، الأستاذ (مشاهدة/إعادة دورة الدفعة في
 * نطاقه)، والطالب (مشاهدة ملفه القرآني) — بنفس نمط 2026_09_16_000002.
 */
return new class extends Migration
{
    private const MANAGER_GRANTS = [
        'quran_batch.view' => 'mosque',
        'quran_batch.update' => 'mosque',
        'quran_settings.view' => 'mosque',
        'quran_settings.update' => 'mosque',
    ];

    private const TEACHER_GRANTS = [
        'quran_batch.view' => 'own',
        'quran_batch.update' => 'own',
    ];

    private const STUDENT_GRANTS = [
        'quran_batch.view' => 'own',
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
