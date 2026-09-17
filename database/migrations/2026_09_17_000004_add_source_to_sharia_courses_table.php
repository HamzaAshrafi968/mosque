<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مصدر إنشاء الدورة: من إدارة الجامع (mosque) أو من مدير الجوامع (super_admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sharia_courses', function (Blueprint $table) {
            $table->string('source')->default('mosque')->after('status');
            $table->index(['tenant_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('sharia_courses', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'source']);
            $table->dropColumn('source');
        });
    }
};
