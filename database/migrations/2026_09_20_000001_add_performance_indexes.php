<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_tenant_session_active_idx');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropIndex('teachers_tenant_session_active_idx');
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropIndex('classrooms_tenant_session_idx');
        });

        Schema::table('quran_recitation_sessions', function (Blueprint $table) {
            $table->dropIndex('quran_sessions_tenant_type_student_idx');
        });

        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropIndex('class_sessions_tenant_schedule_status_idx');
        });

        Schema::table('reward_points', function (Blueprint $table) {
            $table->dropIndex('reward_points_tenant_student_created_idx');
            $table->dropIndex('reward_points_tenant_session_idx');
        });
    }
};
