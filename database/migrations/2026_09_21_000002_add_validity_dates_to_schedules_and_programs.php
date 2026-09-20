<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * مدة صلاحية الحصة: كل حصة قد تكون ليوم/أسبوع/شهر/حتى انتهاء الدورة،
     * فتُخزَّن تواريخ البداية والنهاية على الحصة، وتُحذف تلقائياً عند انتهاء
     * مدتها (`schedules:purge-expired`). القيمة الفارغة = مفتوحة بلا نهاية
     * (سلوك الجداول الحالي). والبرنامج يحمل تاريخ بداية/نهاية دورته ليُستخدم
     * في خيار «حتى انتهاء الدورة».
     */
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('day_of_week');
            $table->date('ends_on')->nullable()->after('starts_on');
            $table->string('duration', 20)->nullable()->after('ends_on');
            $table->index('ends_on', 'schedules_ends_on_index');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('description');
            $table->date('ends_on')->nullable()->after('starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_ends_on_index');
            $table->dropColumn(['starts_on', 'ends_on', 'duration']);
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['starts_on', 'ends_on']);
        });
    }
};
