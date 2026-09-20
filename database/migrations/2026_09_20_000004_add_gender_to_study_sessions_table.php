<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تخصيص الدوام لجنس معين (مثال: دوام أول ذكور / دوام أول إناث).
     * القيمة الفارغة تعني «غير محدد/مختلط» (السلوك السابق)، ويصبح التفرد
     * على (الجامع + الاسم + الجنس) فلا يتكرر نفس الاسم بنفس الجنس.
     */
    public function up(): void
    {
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('name');
            $table->index(['tenant_id', 'name', 'gender'], 'study_sessions_tenant_name_gender_index');
        });
    }

    public function down(): void
    {
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->dropIndex('study_sessions_tenant_name_gender_index');
            $table->dropColumn('gender');
        });
    }
};
