<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط طالب الدورة بسجل الطالب الرسمي (اختياري) + حالة حفظه في الدورة.
 *
 * الطالب المضاف يدوياً يبقى بلا student_id (سجل مستقل كما كان)، والطالب
 * المسجَّل من قائمة الطلاب الموجودين يحمل رابطاً لسجله مع نسخ بياناته.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sharia_course_students', function (Blueprint $table) {
            $table->foreignUuid('student_id')->nullable()->after('course_id')->constrained('students')->nullOnDelete();
            $table->string('memorization_status')->nullable()->after('notes'); // not_memorized | memorized | half_memorized | parts_memorized
            $table->text('memorization_notes')->nullable()->after('memorization_status');
            $table->foreignUuid('memorization_updated_by')->nullable()->after('memorization_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('memorization_updated_at')->nullable()->after('memorization_updated_by');

            $table->unique(['course_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sharia_course_students', function (Blueprint $table) {
            $table->dropUnique(['course_id', 'student_id']);
            $table->dropConstrainedForeignId('student_id');
            $table->dropConstrainedForeignId('memorization_updated_by');
            $table->dropColumn(['memorization_status', 'memorization_notes', 'memorization_updated_at']);
        });
    }
};
