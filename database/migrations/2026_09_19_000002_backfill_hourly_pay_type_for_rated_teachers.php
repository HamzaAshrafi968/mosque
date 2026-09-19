<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * كل أستاذ له سعر ساعة مسجّل يُحوَّل إلى نوع الأجر «بالساعة» حتى يُحتسب راتبه
 * من فترات عمله (نفس سلوك إضافة سعر جديد عبر HourlyRateService).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('teachers')
            ->whereIn('id', function ($query) {
                $query->select('teacher_id')->from('hourly_rates')->distinct();
            })
            ->where(fn ($query) => $query->whereNull('pay_type')->orWhere('pay_type', '!=', 'hourly'))
            ->update(['pay_type' => 'hourly']);
    }

    public function down(): void
    {
        // لا تراجع: التحويل مقصود ولا يمكن تمييز من كان شهرياً قبل الترحيل.
    }
};
