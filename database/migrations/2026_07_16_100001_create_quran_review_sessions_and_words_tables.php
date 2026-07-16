<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_review_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('surah_id')->constrained('quran_surahs')->cascadeOnDelete();
            $table->unsignedInteger('from_ayah');
            $table->unsignedInteger('to_ayah');
            $table->unsignedInteger('total_words')->default(0);
            $table->unsignedInteger('correct_words')->default(0);
            $table->unsignedInteger('incorrect_words')->default(0);
            $table->unsignedInteger('hesitation_words')->default(0);
            $table->unsignedInteger('tajweed_error_words')->default(0);
            $table->unsignedInteger('added_words')->default(0);
            $table->unsignedInteger('forgotten_words')->default(0);
            $table->decimal('mastery_percentage', 5, 2)->default(100);
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'teacher_id', 'date']);
            $table->index(['tenant_id', 'student_id', 'date']);
            $table->index(['tenant_id', 'surah_id']);
        });

        Schema::create('quran_review_words', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('review_session_id')->constrained('quran_review_sessions')->cascadeOnDelete();
            $table->foreignUuid('ayah_id')->constrained('quran_ayahs')->cascadeOnDelete();
            $table->unsignedInteger('word_position');
            $table->string('word_text');
            $table->enum('status', [
                'correct',
                'incorrect',
                'hesitation',
                'tajweed_error',
                'added',
                'forgotten',
                'unreviewed',
            ])->default('unreviewed');
            $table->string('error_type')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'review_session_id']);
            $table->index(['review_session_id', 'ayah_id', 'word_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_review_words');
        Schema::dropIfExists('quran_review_sessions');
    }
};
