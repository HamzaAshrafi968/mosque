<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sharia_course_students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained('sharia_courses')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();          // male | female
            $table->date('birth_date')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');   // active | inactive
            $table->timestamps();

            $table->index(['tenant_id', 'course_id', 'status']);
            $table->index(['tenant_id', 'course_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sharia_course_students');
    }
};
