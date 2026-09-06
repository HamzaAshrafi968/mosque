<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // التسميع: every record is assigned by a teacher to a student.
        Schema::create('quran_recitation_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('new');          // new | revision
            $table->date('date');
            $table->decimal('amount', 7, 2)->default(0);      // المقدار
            $table->string('recited_portion')->nullable();    // اسم المقروء
            $table->string('result')->nullable();             // excellent | very_good | good | needs_review
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'student_id', 'date']);
            $table->index(['tenant_id', 'teacher_id', 'date']);
            $table->index(['tenant_id', 'type', 'date']);
        });

        // إتمام حفظ القرآن: recorded, then confirmed (audited event).
        Schema::create('quran_completions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');     // pending | confirmed
            $table->date('completed_at')->nullable();
            $table->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'student_id']);
            $table->index(['tenant_id', 'status']);
        });

        // حافظ: extension profile of a Student who completed the Quran
        // (never a duplicate person — history stays on the same student row).
        Schema::create('hafiz_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->boolean('mujaz')->default(false);         // حصل على الإجازة
            $table->string('mujiz')->nullable();              // من منحه الإجازة
            $table->string('riwayah')->nullable();            // الرواية
            $table->boolean('ijazah_jazariyyah')->default(false);
            $table->text('scientific_certificate')->nullable();
            $table->text('sharia_courses')->nullable();
            $table->text('training_courses')->nullable();
            $table->text('sharia_academic_study')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hafiz_profiles');
        Schema::dropIfExists('quran_completions');
        Schema::dropIfExists('quran_recitation_sessions');
    }
};
