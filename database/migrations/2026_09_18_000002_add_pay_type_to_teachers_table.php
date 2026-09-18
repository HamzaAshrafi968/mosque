<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نوع الأجر لكل أستاذ: `monthly` (راتب شهري ثابت — الافتراضي للبيانات
 * القائمة) أو `hourly` (يُحتسب من فترات العمل الفعلية × سجل أسعار الساعة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('pay_type', 20)->default('monthly')->after('monthly_salary');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('pay_type');
        });
    }
};
