<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * محاولات الطلاب: محاولة واحدة لكل طالب لكل امتحان (فهرس فريد)،
 * والمؤقت يُحسب من started_at، والتسليم يثبّت submitted_at و score.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->string('status')->default('in_progress'); // in_progress | submitted | graded
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
            $table->index(['tenant_id', 'student_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
