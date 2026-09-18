<?php

use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Database\Migrations\Migration;

/**
 * منح الطالب صلاحية مشاهدة صفحات المصحف (`quran.tasmee.view` بنطاق own)
 * ليتمكن من فتح صفحات القرآن ومتابعتها أثناء الاستماع في «برامج الاستماع»
 * (تدريبي/إجازة/تأهيلي) — الصفحات للقراءة فقط والوصول عبر مسارات
 * `quran/pages` المشتركة (auth + permission).
 */
return new class extends Migration
{
    private const STUDENT_GRANTS = [
        'quran.tasmee.view' => 'own',
    ];

    public function up(): void
    {
        app(RoleService::class)->ensurePermissionCatalog();

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
