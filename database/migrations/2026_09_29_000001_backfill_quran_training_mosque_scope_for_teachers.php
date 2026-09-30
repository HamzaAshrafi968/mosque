<?php

use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Database\Migrations\Migration;

/**
 * صفحات «البرنامج التأهيلي/الإجازة/القراءات» عند الأستاذ تعمل مثل صفحة
 * مدير الجامع: المنح الافتراضية لدور الأستاذ تصبح بنطاق الجامع بدل النطاق
 * الخاص، فيرى الأستاذ كل طلاب الجامع وبرامجهم. يمكن لمدير المساجد إعادة
 * تقييد أستاذ بعينه إلى نطاقه الخاص عبر مصفوفة الصلاحيات (permission_user)
 * ويتبعها المتحكم (QuranListeningProgramController) تلقائياً.
 */
return new class extends Migration
{
    private const CODES = [
        'quran_training.view',
        'quran_training.update',
        'quran_training.listen',
        'quran_training.test',
        'quran_training.enroll',
    ];

    public function up(): void
    {
        app(RoleService::class)->ensurePermissionCatalog();

        $permissionIds = Permission::whereIn('code', self::CODES)->pluck('id', 'code');

        if ($permissionIds->isEmpty()) {
            return;
        }

        Role::where('code', RoleService::ROLE_TEACHER)->each(function (Role $role) use ($permissionIds) {
            foreach ($permissionIds as $code => $id) {
                if ($role->permissions()->where('permissions.code', $code)->exists()) {
                    $role->permissions()->updateExistingPivot($id, ['scope' => 'mosque']);
                }
            }
        });
    }

    public function down(): void
    {
        $permissionIds = Permission::whereIn('code', self::CODES)->pluck('id', 'code');

        if ($permissionIds->isEmpty()) {
            return;
        }

        Role::where('code', RoleService::ROLE_TEACHER)->each(function (Role $role) use ($permissionIds) {
            foreach ($permissionIds as $code => $id) {
                if ($role->permissions()->where('permissions.code', $code)->exists()) {
                    $role->permissions()->updateExistingPivot($id, ['scope' => 'own']);
                }
            }
        });
    }
};
