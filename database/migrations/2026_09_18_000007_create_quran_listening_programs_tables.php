<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «برامج الاستماع» (تدريبي/إجازة/تأهيلي): استماع أجزاء + اختبار كل 5 أجزاء.
 *
 * - 30 جزءاً ÷ 5 = 6 دفعات لكل برنامج.
 * - البرنامج يُلحق بالتحاق البرنامج الأم (program_enrollments) للبرنامج
 *   التأهيلي وبرنامج الإجازة، ويُنشأ يدوياً للبرنامج التدريبي.
 * - تُشتق حالة الدفعة من عناصر أجزائها، والاختبار يُسجَّل يدوياً لكل جزء
 *   (ناجح/يحتاج إعادة) في جدول quran_listening_tests الموجود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quran_listening_programs')) {
            Schema::create('quran_listening_programs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
                $table->foreignUuid('enrollment_id')->nullable()->constrained('program_enrollments')->nullOnDelete();
                $table->string('type', 30);
                $table->string('status', 30)->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'student_id', 'type', 'status']);
            });
        }

        if (! Schema::hasTable('quran_listening_program_batches')) {
            Schema::create('quran_listening_program_batches', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('program_id')->constrained('quran_listening_programs')->cascadeOnDelete();
                $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
                $table->unsignedTinyInteger('batch_number');
                $table->unsignedTinyInteger('from_juz');
                $table->unsignedTinyInteger('to_juz');
                $table->string('status', 30)->default('locked');
                $table->foreignUuid('last_test_id')->nullable()->constrained('quran_listening_tests')->nullOnDelete();
                $table->timestamp('passed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['program_id', 'batch_number']);
                $table->index(['tenant_id', 'student_id', 'status'], 'quran_prog_batches_student_status_idx');
            });
        }

        if (! Schema::hasTable('quran_listening_program_items')) {
            Schema::create('quran_listening_program_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('program_id')->constrained('quran_listening_programs')->cascadeOnDelete();
                $table->foreignUuid('batch_id')->constrained('quran_listening_program_batches')->cascadeOnDelete();
                $table->unsignedTinyInteger('juz');
                $table->unsignedSmallInteger('from_page');
                $table->unsignedSmallInteger('to_page');
                $table->string('status', 30)->default('locked');
                $table->timestamp('listened_at')->nullable();
                $table->foreignUuid('listened_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('listen_seconds')->default(0);
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->string('last_result', 20)->nullable();
                $table->timestamp('passed_at')->nullable();
                $table->foreignUuid('passed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['batch_id', 'juz']);
                $table->index(['tenant_id', 'program_id']);
            });
        }

        if (! Schema::hasColumn('quran_listening_tests', 'listening_batch_id')) {
            Schema::table('quran_listening_tests', function (Blueprint $table) {
                $table->foreignUuid('listening_batch_id')
                    ->nullable()
                    ->after('batch_id')
                    ->constrained('quran_listening_program_batches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quran_listening_tests', 'listening_batch_id')) {
            Schema::table('quran_listening_tests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('listening_batch_id');
            });
        }

        Schema::dropIfExists('quran_listening_program_items');
        Schema::dropIfExists('quran_listening_program_batches');
        Schema::dropIfExists('quran_listening_programs');
    }
};
