<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ربط الأستاذ بأكثر من دوام (مثال: الأول والثالث دون الثاني).
 *
 * يبقى teachers.study_session_id هو الدوام الأساسي (أول اختيار) للتوافق مع
 * بقية الاستعلامات، والجدول الوسيط هو المرجع لكل دوامات الأستاذ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_session_teacher', function (Blueprint $table) {
            $table->foreignUuid('study_session_id')->constrained('study_sessions')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['study_session_id', 'teacher_id']);
        });

        // ترحيل الدوام الحالي لكل أستاذ إلى الجدول الجديد.
        $now = now();

        DB::table('teachers')
            ->whereNotNull('study_session_id')
            ->select(['id', 'study_session_id'])
            ->orderBy('id')
            ->chunk(200, function ($teachers) use ($now) {
                DB::table('study_session_teacher')->insertOrIgnore(
                    $teachers->map(fn ($teacher) => [
                        'study_session_id' => $teacher->study_session_id,
                        'teacher_id' => $teacher->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_session_teacher');
    }
};
