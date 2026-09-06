<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // اللقاءات الإيمانية
        Schema::create('faith_meetings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->foreignUuid('supervisor_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignUuid('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('location')->nullable();
            $table->text('general_notes')->nullable();
            $table->string('status')->default('scheduled');   // scheduled | completed | cancelled
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'supervisor_id']);
            $table->index(['tenant_id', 'teacher_id']);
        });

        // Students explicitly selected for each meeting + attendance.
        Schema::create('faith_meeting_students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('meeting_id')->constrained('faith_meetings')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('attendance_status')->nullable();  // attended | absent | excused
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'meeting_id', 'student_id']);
            $table->index(['tenant_id', 'meeting_id']);
        });

        // General notes, student notes, suggestions and action items.
        Schema::create('faith_meeting_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('meeting_id')->constrained('faith_meetings')->cascadeOnDelete();
            $table->foreignUuid('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note_type')->default('note');     // note | suggestion | action_item
            $table->text('content');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status')->nullable();             // pending | completed (action items)
            $table->timestamps();

            $table->index(['tenant_id', 'meeting_id']);
            $table->index(['tenant_id', 'student_id']);
        });

        // Recurring meeting templates created by administrators.
        Schema::create('faith_meeting_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faith_meeting_templates');
        Schema::dropIfExists('faith_meeting_notes');
        Schema::dropIfExists('faith_meeting_students');
        Schema::dropIfExists('faith_meetings');
    }
};
