<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «مراجعة 5»: رأس المراجعة (طالب + أستاذ + دوام + تاريخ) وعناصرها الخمسات.
 * كل خمسة = ٥ صفحات (والأخيرة تأخذ باقي الجزء) وتُنجَز من الأستاذ المكلَّف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_khamsa_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignUuid('study_session_id')->nullable()->constrained('study_sessions')->nullOnDelete();
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('assigned_at');
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index(['teacher_id', 'status']);
            $table->index('study_session_id');
        });

        Schema::create('quran_khamsa_review_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('review_id')->constrained('quran_khamsa_reviews')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->unsignedTinyInteger('juz');
            $table->unsignedTinyInteger('khamsa');
            $table->unsignedSmallInteger('from_page');
            $table->unsignedSmallInteger('to_page');
            $table->string('status', 20)->default('pending');
            $table->string('result', 20)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('quran_review_session_id')->nullable()->constrained('quran_review_sessions')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['review_id', 'juz', 'khamsa']);
            $table->index(['tenant_id', 'status']);
            $table->index(['juz', 'khamsa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_khamsa_review_items');
        Schema::dropIfExists('quran_khamsa_reviews');
    }
};
