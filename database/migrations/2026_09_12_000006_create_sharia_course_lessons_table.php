<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sharia_course_lessons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained('sharia_courses')->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('lesson');     // lesson | lecture
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->foreignUuid('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'course_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sharia_course_lessons');
    }
};
