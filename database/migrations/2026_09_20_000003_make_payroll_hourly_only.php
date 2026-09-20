<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الراتب بالساعات فقط: لا وجود لمفهوم «الراتب الشهري» ولا «نوع الأجر».
 * الإجمالي = Σ (دقائق كل فترة عمل × سعر الساعة في تاريخها) ÷ 60.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->repairStudentsActiveIndex();

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('monthly_salary');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('pay_type');
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn('pay_type_snapshot');
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn('monthly_salary_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->decimal('monthly_salary', 12, 2)->nullable()->after('hired_at');
            $table->string('pay_type', 20)->default('hourly')->after('monthly_salary');
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->string('pay_type_snapshot', 20)->default('hourly')->after('total_minutes');
            $table->decimal('monthly_salary_snapshot', 12, 2)->nullable()->after('pay_type_snapshot');
        });
    }

    /**
     * إصلاح فهرس أداء الطلاب إن كان منشأً على عمود غير موجود (`is_active`)
     * من نسخة سابقة من ترحيل الفهارس — كان يمنع أي تعديل على مخطط SQLite.
     */
    private function repairStudentsActiveIndex(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        try {
            Schema::table('students', function (Blueprint $table) {
                $table->dropIndex('students_tenant_session_active_idx');
            });
        } catch (Throwable) {
            // الفهرس غير موجود أو سليم أصلاً — المتابعة.
        }

        Schema::table('students', function (Blueprint $table) {
            $table->index(['tenant_id', 'study_session_id', 'status'], 'students_tenant_session_active_idx');
        });
    }
};
