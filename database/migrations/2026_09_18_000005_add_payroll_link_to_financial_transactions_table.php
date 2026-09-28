<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط دفعات الرواتب بكشف الشهر (`payroll_period_id`) وتسجيل طريقة الدفع.
 * تبقى الدفعات صفوفاً في السجل المالي (المصدر) ويُضاف الربط فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->foreignUuid('payroll_period_id')->nullable()->after('reverses_id')
                ->constrained('payroll_periods')->nullOnDelete();
            $table->string('payment_method', 50)->nullable()->after('reference');
            $table->index(['tenant_id', 'payroll_period_id']);
        });
    }

    public function down(): void
    {
        try {
            Schema::table('financial_transactions', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'payroll_period_id']);
            });
        } catch (QueryException) {
            // MariaDB يبقي الفهرس لخدمة مفتاح tenant_id الأجنبي؛ يُحذف مع الجدول لاحقاً.
        }

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_period_id');
            $table->dropColumn('payment_method');
        });
    }
};
