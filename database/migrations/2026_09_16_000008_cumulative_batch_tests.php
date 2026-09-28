<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * الاختبار التراكمي وخمسات إعادة رسوب الاختبار:
 *
 * - نتيجة الاختبار التراكمي تُسجَّل لكل جزء (1..2k) وليست مرتبطة بعنصر خطة،
 *   فيصبح plan_item_id اختيارياً مع قيد فريد (test_id, juz) بدل (test_id, plan_item_id).
 * - رأس مراجعة 5 يحمل نوعاً: خمسات ما بعد الحفظ أو خمسات إعادة رسوب الاختبار.
 * - الدفعة ترتبط بمراجعة الإعادة المولّدة تلقائياً عند الرسوب.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL: مفتاح test_id الأجنبي يحتاج فهرساً داعماً مستقلاً قبل حذف
        // القيد الفريد المركّب (test_id, plan_item_id).
        Schema::table('quran_listening_test_items', function (Blueprint $table) {
            $table->index('test_id', 'qlti_test_id_index');
        });

        Schema::table('quran_listening_test_items', function (Blueprint $table) {
            $table->dropForeign(['plan_item_id']);
        });

        Schema::table('quran_listening_test_items', function (Blueprint $table) {
            $table->dropUnique(['test_id', 'plan_item_id']);
        });

        Schema::table('quran_listening_test_items', function (Blueprint $table) {
            $table->uuid('plan_item_id')->nullable()->change();
        });

        Schema::table('quran_listening_test_items', function (Blueprint $table) {
            $table->foreign('plan_item_id')
                ->references('id')
                ->on('quran_listening_plan_items')
                ->nullOnDelete();
        });

        Schema::table('quran_khamsa_reviews', function (Blueprint $table) {
            $table->string('type', 30)->default('post_memorization')->after('status');
        });

        Schema::table('quran_memorization_batches', function (Blueprint $table) {
            $table->foreignUuid('retake_review_id')
                ->nullable()
                ->after('review_5_id')
                ->constrained('quran_khamsa_reviews')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('quran_memorization_batches', 'retake_review_id')) {
            Schema::table('quran_memorization_batches', function (Blueprint $table) {
                $table->dropConstrainedForeignId('retake_review_id');
            });
        }

        if (Schema::hasColumn('quran_khamsa_reviews', 'type')) {
            Schema::table('quran_khamsa_reviews', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }

        DB::table('quran_listening_test_items')->whereNull('plan_item_id')->delete();

        if ($this->hasPlanItemForeignKey()) {
            Schema::table('quran_listening_test_items', function (Blueprint $table) {
                $table->dropForeign(['plan_item_id']);
            });
        }

        Schema::table('quran_listening_test_items', function (Blueprint $table) {
            $table->uuid('plan_item_id')->nullable(false)->change();
        });

        Schema::table('quran_listening_test_items', function (Blueprint $table) {
            $table->foreign('plan_item_id')
                ->references('id')
                ->on('quran_listening_plan_items')
                ->cascadeOnDelete();
            $table->unique(['test_id', 'plan_item_id']);
        });
    }

    private function hasPlanItemForeignKey(): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return collect(DB::select("pragma foreign_key_list('quran_listening_test_items')"))
                ->contains(fn ($foreignKey) => $foreignKey->from === 'plan_item_id');
        }

        return DB::table('information_schema.key_column_usage')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', 'quran_listening_test_items')
            ->where('column_name', 'plan_item_id')
            ->exists();
    }
};
