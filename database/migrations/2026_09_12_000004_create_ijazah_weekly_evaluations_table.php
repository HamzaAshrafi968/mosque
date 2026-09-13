<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ijazah_weekly_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7);                    // YYYY-MM
            $table->unsignedTinyInteger('week');           // 1..4
            $table->date('week_start')->nullable();
            $table->date('week_end')->nullable();
            $table->decimal('amount', 6, 2);
            $table->string('recited_portion')->nullable();
            $table->string('result')->default('passed');   // passed | needs_review | failed
            $table->foreignUuid('evaluated_by')->nullable()->constrained('teachers')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id', 'month', 'week']);
            $table->index(['tenant_id', 'student_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ijazah_weekly_evaluations');
    }
};
