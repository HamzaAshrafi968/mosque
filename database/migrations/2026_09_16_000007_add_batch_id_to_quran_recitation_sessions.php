<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط تسميع «الحفظ الجديد» بدفعة الحفظ التي يقع نطاقه داخلها.
 *
 * - تسميع type=new يُربط تلقائياً بالدفعة (عبر رقم الجزء المشتق من الصفحة).
 * - تسميع type=revision يبقى بلا دفعة (مراجعة محفوظ حرة).
 * - بذلك يُعرض سجل التسميع داخل دورة الدفعة في مركز «دفعات الحفظ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_recitation_sessions', function (Blueprint $table) {
            $table->foreignUuid('batch_id')
                ->nullable()
                ->after('teacher_id')
                ->constrained('quran_memorization_batches')
                ->nullOnDelete();
            $table->index(['student_id', 'batch_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('quran_recitation_sessions', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'batch_id', 'type']);
            $table->dropConstrainedForeignId('batch_id');
        });
    }
};
