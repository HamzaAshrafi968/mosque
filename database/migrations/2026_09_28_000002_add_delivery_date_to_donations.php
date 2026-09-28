<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تاريخ وتسليم التبرع/المساهمة: موعد (يوم وساعة) يتفق عليه
 * المتبرع مع إدارة الجامع (إجباري في نماذج التقديم).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dateTime('delivery_date')->nullable()->after('custom_type');
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn('delivery_date');
            $table->string('title')->change();
        });
    }
};
