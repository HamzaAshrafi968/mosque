<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * كشف الراتب الشهري لكل أستاذ.
 *
 * - الحالة `open`: تُحتسب القيم حياً من فترات العمل والسعر الساري.
 * - الحالة `closed`: لقطة ثابتة لا تتأثر بأي تغيير لاحق (سعر/فترات).
 * - `paid_amount` ذاكرة مؤقتة تُحدَّث مع كل دفعة/عكس، والمصدر هو السجل المالي
 *   (`financial_transactions.payroll_period_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('total_minutes')->default(0);
            $table->string('pay_type_snapshot', 20)->default('monthly');
            $table->decimal('monthly_salary_snapshot', 12, 2)->nullable();
            $table->decimal('hourly_rate_snapshot', 12, 2)->nullable();
            $table->json('rate_breakdown')->nullable();
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('open');
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'teacher_id', 'year', 'month']);
            $table->index(['tenant_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
