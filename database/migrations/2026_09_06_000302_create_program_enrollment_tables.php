<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enrollment in the qualifying / ijazah programs (automatic transitions).
        Schema::create('program_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('program_type');                   // qualifying | ijazah
            $table->date('started_at');
            $table->date('completed_at')->nullable();
            $table->string('status')->default('active');       // active | completed
            $table->foreignUuid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'program_type', 'status']);
            $table->index(['tenant_id', 'student_id', 'program_type']);
        });

        // تقييم أسبوعي للبرنامج التأهيلي — every week gets a new row.
        Schema::create('qualifying_weekly_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->date('week_end');
            $table->decimal('amount', 7, 2)->default(0);
            $table->string('recited_portion')->nullable();
            $table->string('result')->default('passed');       // passed | needs_review | failed
            $table->foreignUuid('evaluated_by')->nullable()->constrained('teachers')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'student_id', 'week_start']);
            $table->index(['tenant_id', 'evaluated_by']);
        });

        // تقييم شهري لبرنامج الإجازة — one historical row per student & month.
        Schema::create('ijazah_monthly_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);                        // YYYY-MM
            $table->decimal('amount', 7, 2)->default(0);
            $table->string('recited_portion')->nullable();
            $table->string('result')->default('passed');       // passed | needs_review | failed
            $table->foreignUuid('evaluated_by')->nullable()->constrained('teachers')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id', 'month']);
            $table->index(['tenant_id', 'evaluated_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ijazah_monthly_evaluations');
        Schema::dropIfExists('qualifying_weekly_evaluations');
        Schema::dropIfExists('program_enrollments');
    }
};
