<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // الاختبارات الشهرية للحفاظ: one historical row per hafiz & month.
        Schema::create('hafiz_monthly_exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);                        // YYYY-MM
            $table->string('exam_status')->default('not_tested'); // not_tested | tested | passed | failed
            $table->decimal('grade', 5, 2)->nullable();        // من 100
            $table->foreignUuid('supervisor_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->date('exam_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id', 'month']);
            $table->index(['tenant_id', 'month', 'exam_status']);
            $table->index(['tenant_id', 'supervisor_id']);
        });

        // Portions a failed hafiz must repeat + repetition tracking.
        Schema::create('hafiz_exam_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('exam_id')->constrained('hafiz_monthly_exams')->cascadeOnDelete();
            $table->foreignUuid('from_surah')->nullable()->constrained('quran_surahs')->nullOnDelete();
            $table->unsignedInteger('from_ayah')->nullable();
            $table->foreignUuid('to_surah')->nullable()->constrained('quran_surahs')->nullOnDelete();
            $table->unsignedInteger('to_ayah')->nullable();
            $table->unsignedInteger('juz')->nullable();
            $table->decimal('amount', 7, 2)->nullable();
            $table->string('status')->default('pending');      // pending | completed | approved
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'exam_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hafiz_exam_revisions');
        Schema::dropIfExists('hafiz_monthly_exams');
    }
};
