<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('section_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active'); // active|inactive|transferred|completed
            $table->date('enrolled_at')->nullable();
            $table->date('left_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id', 'section_id']);
            $table->index(['tenant_id', 'section_id', 'status']);
            $table->index(['tenant_id', 'student_id']);
        });

        Schema::create('section_teachers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('section_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('lead'); // lead|assistant
            $table->string('status')->default('active'); // active|inactive
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'section_id', 'teacher_id']);
            $table->index(['tenant_id', 'teacher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_teachers');
        Schema::dropIfExists('section_students');
    }
};
