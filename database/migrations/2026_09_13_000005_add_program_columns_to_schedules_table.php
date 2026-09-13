<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ربط الحصص الدراسية بالتخصص (program)، والفترة (program_period)،
 * والدوام (study_session). المادة تصبح اختيارية حين تكون الحصة لبرنامج.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignUuid('program_id')->nullable()->after('teacher_id')->constrained('programs')->nullOnDelete();
            $table->foreignUuid('program_period_id')->nullable()->after('program_id')->constrained('program_periods')->nullOnDelete();
            $table->foreignUuid('study_session_id')->nullable()->after('program_period_id')->constrained('study_sessions')->nullOnDelete();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignUuid('subject_id')->nullable()->change();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->index(['tenant_id', 'program_id', 'day_of_week']);
            $table->index(['tenant_id', 'study_session_id', 'day_of_week']);
        });

        $this->backfillStudySessions();
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'program_id', 'day_of_week']);
            $table->dropIndex(['tenant_id', 'study_session_id', 'day_of_week']);
        });

        if (! DB::table('schedules')->whereNull('subject_id')->exists()) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->foreignUuid('subject_id')->nullable(false)->change();
            });
        }

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('study_session_id');
            $table->dropConstrainedForeignId('program_period_id');
            $table->dropConstrainedForeignId('program_id');
        });
    }

    /** Existing rows inherit the shift from their section, then their teacher. */
    private function backfillStudySessions(): void
    {
        DB::table('schedules')
            ->select('id', 'section_id', 'teacher_id')
            ->whereNull('study_session_id')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $sessionId = $row->section_id
                        ? DB::table('sections')->where('id', $row->section_id)->value('study_session_id')
                        : null;

                    $sessionId ??= $row->teacher_id
                        ? DB::table('teachers')->where('id', $row->teacher_id)->value('study_session_id')
                        : null;

                    if ($sessionId !== null) {
                        DB::table('schedules')->where('id', $row->id)->update(['study_session_id' => $sessionId]);
                    }
                }
            });
    }
};
