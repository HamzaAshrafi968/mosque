<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «خطة الاستماع والاختبار»:
 *
 * - رأس الخطة: طالب + أستاذ + دوام + عدد الأجزاء المفتوحة في الدفعة (gate_size).
 * - عناصر الخطة: جزء واحد بنطاق صفحات من–إلى داخل حدود الجزء فقط.
 * - الاختبارات: محاولة اختبار واحدة لكل دفعة مفتوحة، ونتيجة كل جزء داخلها
 *   (ناجح/يحتاج إعادة) مع لقطة لنطاق الصفحات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_listening_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignUuid('study_session_id')->nullable()->constrained('study_sessions')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->unsignedTinyInteger('gate_size')->default(1);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'student_id', 'status']);
            $table->index(['tenant_id', 'teacher_id', 'status']);
            $table->index('study_session_id');
        });

        Schema::create('quran_listening_plan_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('quran_listening_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedTinyInteger('juz');
            $table->unsignedSmallInteger('from_page');
            $table->unsignedSmallInteger('to_page');
            $table->string('status', 20)->default('locked');
            $table->timestamp('listened_at')->nullable();
            $table->foreignUuid('listened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('listen_seconds')->default(0);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_result', 20)->nullable();
            $table->timestamp('passed_at')->nullable();
            $table->foreignUuid('passed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'juz']);
            $table->index(['tenant_id', 'status']);
            $table->index(['plan_id', 'position']);
        });

        Schema::create('quran_listening_tests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('quran_listening_plans')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('tested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tested_at')->nullable();
            $table->string('result', 20);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'plan_id']);
            $table->index(['tenant_id', 'student_id']);
        });

        Schema::create('quran_listening_test_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('test_id')->constrained('quran_listening_tests')->cascadeOnDelete();
            $table->foreignUuid('plan_item_id')->constrained('quran_listening_plan_items')->cascadeOnDelete();
            $table->unsignedTinyInteger('juz');
            $table->unsignedSmallInteger('from_page');
            $table->unsignedSmallInteger('to_page');
            $table->string('result', 20);
            $table->boolean('needs_repeat')->default(false);
            $table->foreignUuid('quran_review_session_id')->nullable()->constrained('quran_review_sessions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['test_id', 'plan_item_id']);
            $table->index(['tenant_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_listening_test_items');
        Schema::dropIfExists('quran_listening_tests');
        Schema::dropIfExists('quran_listening_plan_items');
        Schema::dropIfExists('quran_listening_plans');
    }
};
