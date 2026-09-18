<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فترات العمل الفعلية (Work Slots) — المصدر الفعلي لاحتساب الرواتب.
 *
 * الجدول الأسبوعي المتكرر (teacher_work_hours) يبقى «المخطط»، وهذا الجدول
 * هو «الفعلي»: تاريخ محدد + وقت بداية ونهاية + مدة محسوبة بالدقائق.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'teacher_id', 'date']);
            $table->index(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_slots');
    }
};
