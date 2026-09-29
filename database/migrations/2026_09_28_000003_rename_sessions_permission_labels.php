<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * توحيد التسمية على «الدوام»: تحديث وسم «مشاهدة الدوامات» القديم في
     * قواعد البيانات القائمة ليطابق الكتالوج الجديد.
     */
    public function up(): void
    {
        DB::table('permissions')
            ->where('code', 'sessions.view')
            ->where('label', 'مشاهدة الدوامات')
            ->update(['label' => 'مشاهدة الدوام']);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('code', 'sessions.view')
            ->where('label', 'مشاهدة الدوام')
            ->update(['label' => 'مشاهدة الدوامات']);
    }
};
