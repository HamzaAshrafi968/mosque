<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ربط الدورة الشرعية بأكثر من مشرف (مشرفون متعددون).
 *
 * يبقى sharia_courses.supervisor_id مؤقتاً حتى تُعبأ البيانات في الجدول
 * الوسيط ثم يُحذف في الترحيل التالي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sharia_course_supervisor', function (Blueprint $table) {
            $table->foreignUuid('course_id')->constrained('sharia_courses')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['course_id', 'teacher_id']);
        });

        // ترحيل المشرف الحالي لكل دورة إلى الجدول الجديد.
        $now = now();

        DB::table('sharia_courses')
            ->whereNotNull('supervisor_id')
            ->select(['id', 'supervisor_id'])
            ->orderBy('id')
            ->chunk(200, function ($courses) use ($now) {
                DB::table('sharia_course_supervisor')->insertOrIgnore(
                    $courses->map(fn ($course) => [
                        'course_id' => $course->id,
                        'teacher_id' => $course->supervisor_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('sharia_course_supervisor');
    }
};
