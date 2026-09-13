<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مصدر خيارات خصائص البرنامج: كتابة يدوية، أو اختيار من الطلاب مع مرشّحات
 * (الحالة، الجنس، العمر من/إلى، عدد الأجزاء من/إلى، الصف).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_attributes', function (Blueprint $table) {
            $table->string('options_source')->default('manual')->after('field_type');
            $table->json('options_config')->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('program_attributes', function (Blueprint $table) {
            $table->dropColumn(['options_source', 'options_config']);
        });
    }
};
