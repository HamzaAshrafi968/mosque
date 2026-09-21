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
            $table->unique(['tenant_id', 'section_id', 'date'], 'attendance_sessions_unique');
            $table->index(['tenant_id', 'date', 'status'], 'attendance_sessions_date_status_idx');
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
            $table->unique(['tenant_id', 'attendance_session_id', 'student_id'], 'attendance_records_unique');
            $table->index(['tenant_id', 'student_id'], 'attendance_records_student_idx');
            $table->index(['attendance_session_id', 'status'], 'attendance_records_session_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
    }
};
