<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Manages the "sessions" (الدوامات) permission resource:
     * insert catalog rows + grant them to the default per-mosque manager role.
     */
    public function up(): void
    {
        $rows = [
            ['sessions', 'view', 'مشاهدة الدوامات'],
            ['sessions', 'create', 'إضافة دوام'],
            ['sessions', 'update', 'تعديل دوام'],
            ['sessions', 'delete', 'حذف دوام'],
        ];

        foreach ($rows as [$resource, $action, $label]) {
            $existing = DB::table('permissions')->where('code', $resource.'.'.$action)->first();

            if (! $existing) {
                DB::table('permissions')->insert([
                    'id' => (string) Str::uuid(),
                    'code' => $resource.'.'.$action,
                    'resource' => $resource,
                    'action' => $action,
                    'label' => $label,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $permissionIds = DB::table('permissions')
            ->where('resource', 'sessions')
            ->pluck('id');

        foreach (DB::table('roles')->where('code', 'mosque_manager')->get() as $role) {
            foreach ($permissionIds as $permissionId) {
                $granted = DB::table('permission_role')
                    ->where('role_id', $role->id)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if (! $granted) {
                    DB::table('permission_role')->insert([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                        'scope' => 'mosque',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->where('resource', 'sessions')->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->where('resource', 'sessions')->delete();
    }
};
