<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * فهارس الأداء للاستعلامات الأعلى تكراراً بعد مراجعة الأداء:
     * - قوائم الطلاب/الأساتذة المفلترة بالدوام النشط.
     * - تجميع صفحات التسميع (النوع + الطالب) في شاشات القرآن.
     * - الاستثناءات القادمة لحصة أسبوعية (إلغاء/تأجيل).
     * - نقاط المكافآت لكل طالب بترتيب زمني.
     * - صفوف/شعب كل دوام.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->index(['tenant_id', 'study_session_id', 'status'], 'students_tenant_session_active_idx');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->index(['tenant_id', 'study_session_id', 'is_active'], 'teachers_tenant_session_active_idx');
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->index(['tenant_id', 'study_session_id'], 'classrooms_tenant_session_idx');
        });

        Schema::table('quran_recitation_sessions', function (Blueprint $table) {
            $table->index(['tenant_id', 'type', 'student_id'], 'quran_sessions_tenant_type_student_idx');
        });

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->index(['tenant_id', 'schedule_id', 'status'], 'class_sessions_tenant_schedule_status_idx');
        });

        Schema::table('reward_points', function (Blueprint $table) {
            $table->index(['tenant_id', 'student_id', 'created_at'], 'reward_points_tenant_student_created_idx');
            $table->index(['tenant_id', 'study_session_id'], 'reward_points_tenant_session_idx');
        });
    }

    public function down(): void
    {
        foreach ([
            ['students', 'students_tenant_session_active_idx'],
            ['teachers', 'teachers_tenant_session_active_idx'],
            ['classrooms', 'classrooms_tenant_session_idx'],
            ['quran_recitation_sessions', 'quran_sessions_tenant_type_student_idx'],
            ['class_sessions', 'class_sessions_tenant_schedule_status_idx'],
            ['reward_points', 'reward_points_tenant_student_created_idx'],
            ['reward_points', 'reward_points_tenant_session_idx'],
        ] as [$table, $index]) {
            $this->dropIndexIfExists($table, $index);
        }
    }

    /**
     * التراجع يجب أن يكون آمناً حتى بعد محاولة فاشلة سابقة:
     * يُفحص وجود الفهرس أولاً، ويُتجاهل الفهرس المربوط بمفتاح أجنبي
     * (سيُحذف تلقائياً مع حذف الجدول لاحقاً في سلسلة التراجع).
     */
    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();

        if (! $exists) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $table) use ($index) {
                $table->dropIndex($index);
            });
        } catch (QueryException) {
            // الفهرس مرتبط بمفتاح أجنبي، سيُحذف مع حذف الجدول.
        }
    }
};
