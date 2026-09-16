<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دفعات الحفظ (Memorization Batches):
 *
 * - كل دفعة = جزآن متتاليان (1–2، 3–4، ...، 29–30) — 15 دفعة دائماً.
 * - الدفعة لا تُفتح إلا بعد اجتياز اختبار الدفعة السابقة، وتُشتق حالتها من
 *   الأجزاء المحفوظة فعلياً + مراجعة 5 + نتيجة الاختبار.
 * - يُربط بالدفعة: خطة الاستماع المولّدة تلقائياً، رأس مراجعة 5، وآخر اختبار.
 *
 * ويُوسَّم اختبار خطة الاستماع بحقول اختبار الدفعة: الربط بالدفعة، والدرجة
 * المحسوبة من العناصر، ولقطة حد النجاح وقت تنفيذ الاختبار.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_memorization_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedTinyInteger('batch_number');
            $table->unsignedTinyInteger('from_juz');
            $table->unsignedTinyInteger('to_juz');
            $table->string('status', 30)->default('pending_memorization');
            $table->foreignUuid('review_5_id')->nullable()->constrained('quran_khamsa_reviews')->nullOnDelete();
            $table->foreignUuid('plan_id')->nullable()->constrained('quran_listening_plans')->nullOnDelete();
            $table->foreignUuid('last_test_id')->nullable()->constrained('quran_listening_tests')->nullOnDelete();
            $table->timestamp('passed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'batch_number']);
            $table->index(['tenant_id', 'student_id', 'status']);
        });

        Schema::table('quran_listening_tests', function (Blueprint $table) {
            $table->foreignUuid('batch_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('quran_memorization_batches')
                ->nullOnDelete();
            $table->decimal('score', 5, 2)->nullable()->after('result');
            $table->decimal('passing_percentage', 5, 2)->nullable()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('quran_listening_tests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
            $table->dropColumn(['score', 'passing_percentage']);
        });

        Schema::dropIfExists('quran_memorization_batches');
    }
};
