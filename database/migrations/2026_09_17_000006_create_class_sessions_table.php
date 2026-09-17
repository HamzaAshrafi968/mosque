<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->foreignUuid('study_session_id')->nullable()->constrained('study_sessions')->nullOnDelete();
            $table->foreignUuid('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->date('date');
            $table->string('status')->default('cancelled'); // scheduled | postponed | cancelled | completed
            $table->text('reason')->nullable();
            $table->date('postponed_date')->nullable();
            $table->time('postponed_starts_at')->nullable();
            $table->time('postponed_ends_at')->nullable();
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['section_id', 'schedule_id', 'date']);
            $table->index(['tenant_id', 'date']);
            $table->index(['schedule_id', 'date']);
            $table->index(['teacher_id', 'postponed_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
