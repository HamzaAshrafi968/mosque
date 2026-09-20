<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نطاق الاختبار: دوام كامل (بلا صفوف) أو صف/عدة صفوف عبر exam_classroom.
 * classroom_id يبقى الصف الأساسي للتوافق، ويصبح فارغاً لاختبار الدوام الكامل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignUuid('study_session_id')->nullable()->after('classroom_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->uuid('classroom_id')->nullable()->change();
        });

        Schema::create('exam_classroom', function (Blueprint $table) {
            $table->foreignUuid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('classroom_id')->constrained()->cascadeOnDelete();

            $table->primary(['exam_id', 'classroom_id']);
        });

        DB::table('exams')
            ->whereNotNull('classroom_id')
            ->get(['id', 'classroom_id'])
            ->each(fn ($exam) => DB::table('exam_classroom')->insert([
                'exam_id' => $exam->id,
                'classroom_id' => $exam->classroom_id,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_classroom');

        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('study_session_id');
        });

        $fallbackClassroomId = DB::table('classrooms')->value('id');

        if ($fallbackClassroomId !== null) {
            DB::table('exams')
                ->whereNull('classroom_id')
                ->update(['classroom_id' => $fallbackClassroomId]);
        }

        Schema::table('exams', function (Blueprint $table) {
            $table->uuid('classroom_id')->nullable(false)->change();
        });
    }
};
