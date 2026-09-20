<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تخصيص الإعلان لدوام معين (مثال: كل الدوام الأول). القيمة الفارغة تعني
     * أن الإعلان يخص جميع الدوامات (السلوك السابق).
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignUuid('study_session_id')
                ->nullable()
                ->after('classroom_id')
                ->constrained('study_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('study_session_id');
        });
    }
};
