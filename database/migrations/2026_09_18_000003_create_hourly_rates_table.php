<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجل أسعار الساعة التاريخي لكل أستاذ: سعر ساري من تاريخ إلى تاريخ.
 * لا تداخل بين فترات السعر لنفس الأستاذ (يُفرض في الخدمة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hourly_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'teacher_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hourly_rates');
    }
};
