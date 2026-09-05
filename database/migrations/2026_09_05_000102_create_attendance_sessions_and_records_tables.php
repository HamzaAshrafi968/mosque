<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('section_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('status')->default('completed'); // open|completed
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One attendance-taking event per section per day.
            $table->unique(['tenant_id', 'section_id', 'date']);
            $table->index(['tenant_id', 'date', 'status']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('status'); // present|absent|late|excused
            $table->text('note')->nullable();
            $table->timestamps();

            // One record per student per session.
            $table->unique(['tenant_id', 'attendance_session_id', 'student_id']);
            $table->index(['tenant_id', 'student_id']);
            $table->index(['attendance_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
    }
};
