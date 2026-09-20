<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الاختبار المباشر للأجزاء المحفوظة مسبقاً:
 *
 * الطالب القادم بحفظ سابق يمكن اختباره مباشرة على جزأي الدفعة قبل تكوين
 * خطة استماع/مراجعة 5، لذا يصبح plan_id على quran_listening_tests اختيارياً
 * (الاختبار التراكمي العادي يبقى مرتبطاً بالخطة كما هو).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_listening_tests', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });

        Schema::table('quran_listening_tests', function (Blueprint $table) {
            $table->uuid('plan_id')->nullable()->change();
        });

        Schema::table('quran_listening_tests', function (Blueprint $table) {
            $table->foreign('plan_id')
                ->references('id')
                ->on('quran_listening_plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('quran_listening_tests')->whereNull('plan_id')->delete();

        Schema::table('quran_listening_tests', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });

        Schema::table('quran_listening_tests', function (Blueprint $table) {
            $table->uuid('plan_id')->nullable(false)->change();
        });

        Schema::table('quran_listening_tests', function (Blueprint $table) {
            $table->foreign('plan_id')
                ->references('id')
                ->on('quran_listening_plans')
                ->cascadeOnDelete();
        });
    }
};
