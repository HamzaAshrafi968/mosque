<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نوع المساهمة «غير ذلك»: عمود اختياري يحفظ النوع المخصص
 * الذي يكتبه المتبرع بنفسه عندما يختار «غير ذلك».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->string('custom_type')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn('custom_type');
        });
    }
};
