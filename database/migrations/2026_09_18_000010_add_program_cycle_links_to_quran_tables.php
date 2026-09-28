<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط دورة برامج الاستماع (التأهيلي/الإجازة) بالتسميع:
 *
 * - تسميع «جديد» داخل دفعة برنامج (5 أجزاء) يُربط بـ program_batch_id.
 * - كل جزء من دفعة البرنامج يحتفظ بآخر جلسة تسميع غطّته.
 *
 * لا خمسات ولا خطط استماع في هذه الدورة — التسميع ثم الاختبار التراكمي.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quran_recitation_sessions', 'program_batch_id')) {
            Schema::table('quran_recitation_sessions', function (Blueprint $table) {
                $table->foreignUuid('program_batch_id')
                    ->nullable()
                    ->after('batch_id')
                    ->constrained('quran_listening_program_batches')
                    ->nullOnDelete();
                $table->index(['student_id', 'program_batch_id', 'type']);
            });
        }

        if (! Schema::hasColumn('quran_listening_program_items', 'quran_recitation_session_id')) {
            Schema::table('quran_listening_program_items', function (Blueprint $table) {
                $table->foreignUuid('quran_recitation_session_id')
                    ->nullable()
                    ->after('listen_seconds')
                    ->constrained('quran_recitation_sessions', 'id', 'qlpi_recitation_session_fk')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quran_listening_program_items', 'quran_recitation_session_id')) {
            Schema::table('quran_listening_program_items', function (Blueprint $table) {
                $table->dropForeign('qlpi_recitation_session_fk');
                $table->dropColumn('quran_recitation_session_id');
            });
        }

        if (Schema::hasColumn('quran_recitation_sessions', 'program_batch_id')) {
            try {
                Schema::table('quran_recitation_sessions', function (Blueprint $table) {
                    $table->dropIndex(['student_id', 'program_batch_id', 'type']);
                });
            } catch (QueryException) {
                // MariaDB يبقي الفهرس لخدمة مفتاح student_id الأجنبي؛ يُحذف مع الجدول لاحقاً.
            }

            Schema::table('quran_recitation_sessions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('program_batch_id');
            });
        }
    }
};
