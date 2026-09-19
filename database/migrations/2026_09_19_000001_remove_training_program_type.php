<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * إزالة «البرنامج التدريبي» نهائياً: دوراته ودفعاته وعناصره واختباراته
 * والتحاقاته، مع صلاحية quran_training.create التي لم تبقَ لها وظيفة
 * (لم يعد هناك تسجيل يدوي في برنامج استماع).
 */
return new class extends Migration
{
    public function up(): void
    {
        $programIds = DB::table('quran_listening_programs')
            ->where('type', 'training')
            ->pluck('id');

        $batchIds = $programIds->isEmpty()
            ? collect()
            : DB::table('quran_listening_program_batches')
                ->whereIn('program_id', $programIds)
                ->pluck('id');

        if ($batchIds->isNotEmpty()) {
            DB::table('quran_listening_tests')
                ->whereIn('listening_batch_id', $batchIds)
                ->delete();
        }

        if ($programIds->isNotEmpty()) {
            // الدفعات والعناصر تُحذف تتابعياً (cascadeOnDelete).
            DB::table('quran_listening_programs')->whereIn('id', $programIds)->delete();
        }

        DB::table('program_enrollments')->where('program_type', 'training')->delete();

        $permissionId = DB::table('permissions')
            ->where('code', 'quran_training.create')
            ->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }

    public function down(): void
    {
        // لا رجوع: البرنامج التدريبي أُزيل نهائياً.
    }
};
