<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * برنامج القراءات (القراءات العشر): نوع برنامج اختياري متقدم بجانب
 * التأهيلي والإجازة، ويُدار بنفس محرك دفعات 5 أجزاء.
 *
 * - `quran_listening_programs.reading`: القراءة المختارة من القراءات العشر
 *   (فارغ للتأهيلي/الإجازة).
 * - `program_enrollments.reading`: نفس القراءة على الالتحاق لتسمح بأكثر من
 *   قراءة متوازية لنفس الطالب (الالتحاق فريد لكل طالب + نوع + قراءة).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quran_listening_programs', 'reading')) {
            Schema::table('quran_listening_programs', function (Blueprint $table) {
                $table->string('reading', 30)->nullable()->after('type');
                $table->index(['tenant_id', 'student_id', 'type', 'reading'], 'qlp_tenant_student_type_reading_index');
            });
        }

        if (! Schema::hasColumn('program_enrollments', 'reading')) {
            Schema::table('program_enrollments', function (Blueprint $table) {
                $table->string('reading', 30)->nullable()->after('program_type');
                $table->index(['tenant_id', 'student_id', 'program_type', 'reading'], 'pe_tenant_student_type_reading_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quran_listening_programs', 'reading')) {
            Schema::table('quran_listening_programs', function (Blueprint $table) {
                $table->dropIndex('qlp_tenant_student_type_reading_index');
                $table->dropColumn('reading');
            });
        }

        if (Schema::hasColumn('program_enrollments', 'reading')) {
            Schema::table('program_enrollments', function (Blueprint $table) {
                $table->dropIndex('pe_tenant_student_type_reading_index');
                $table->dropColumn('reading');
            });
        }
    }
};
