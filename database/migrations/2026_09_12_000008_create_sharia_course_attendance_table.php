<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sharia_course_attendance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained('sharia_courses')->cascadeOnDelete();
            $table->foreignUuid('lesson_id')->nullable()->constrained('sharia_course_lessons')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('sharia_course_students')->cascadeOnDelete();
            $table->date('date');
            $table->string('status');                      // present | absent | late | excused
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lesson_id', 'student_id']);
            $table->index(['tenant_id', 'course_id', 'date']);
            $table->index(['tenant_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sharia_course_attendance');
    }
};
