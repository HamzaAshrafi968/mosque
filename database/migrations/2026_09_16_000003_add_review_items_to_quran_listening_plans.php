<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * دمج «مراجعة 5» في «خطة الاستماع والاختبار»:
 *
 * - عنصر الخطة يصبح له نوع: جديد (new) أو مراجعة خمسة (review + khamsa).
 * - مراجعة الخمسة تُنشئ رأس «مراجعة 5» مرتبطاً بالخطة تلقائياً (khamsa_review_id)
 *   ويُربط كل عنصر خطة بعنصر الخمسة المقابل (khamsa_review_item_id).
 * - يمكن ربط جلسة «الاستماع مع المعلم» عند تسجيل الاستماع
 *   (quran_review_session_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_listening_plans', function (Blueprint $table) {
            $table->foreignUuid('khamsa_review_id')
                ->nullable()
                ->after('created_by')
                ->constrained('quran_khamsa_reviews')
                ->nullOnDelete();
        });

        Schema::table('quran_khamsa_reviews', function (Blueprint $table) {
            $table->foreignUuid('listening_plan_id')
                ->nullable()
                ->after('assigned_by')
                ->constrained('quran_listening_plans')
                ->nullOnDelete();
        });

        Schema::table('quran_listening_plan_items', function (Blueprint $table) {
            // MySQL: فهرس داعم لمفتاح plan_id الأجنبي قبل حذف القيد الفريد المركّب.
            $table->index('plan_id', 'qlpi_plan_id_index');
        });

        Schema::table('quran_listening_plan_items', function (Blueprint $table) {
            $table->dropUnique(['plan_id', 'juz']);
        });

        Schema::table('quran_listening_plan_items', function (Blueprint $table) {
            $table->string('type', 10)->default('new')->after('juz');
            $table->unsignedTinyInteger('khamsa')->default(0)->after('type');
            $table->foreignUuid('khamsa_review_item_id')
                ->nullable()
                ->after('passed_by')
                ->constrained('quran_khamsa_review_items')
                ->nullOnDelete();
            $table->foreignUuid('quran_review_session_id')
                ->nullable()
                ->after('khamsa_review_item_id')
                ->constrained('quran_review_sessions')
                ->nullOnDelete();

            $table->unique(['plan_id', 'type', 'juz', 'khamsa']);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('quran_listening_plan_items', 'type')) {
            try {
                Schema::table('quran_listening_plan_items', function (Blueprint $table) {
                    $table->dropUnique(['plan_id', 'type', 'juz', 'khamsa']);
                });
            } catch (QueryException) {
                // MariaDB يبقي الفريد لخدمة مفتاح plan_id الأجنبي؛ يُحذف مع الجدول لاحقاً.
            }

            Schema::table('quran_listening_plan_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('khamsa_review_item_id');
                $table->dropConstrainedForeignId('quran_review_session_id');
                $table->dropColumn(['type', 'khamsa']);
            });
        }

        $this->deduplicatePlanItems();

        if (! $this->hasPlanJuzUnique()) {
            Schema::table('quran_listening_plan_items', function (Blueprint $table) {
                $table->unique(['plan_id', 'juz']);
            });
        }

        if (Schema::hasColumn('quran_khamsa_reviews', 'listening_plan_id')) {
            Schema::table('quran_khamsa_reviews', function (Blueprint $table) {
                $table->dropConstrainedForeignId('listening_plan_id');
            });
        }

        if (Schema::hasColumn('quran_listening_plans', 'khamsa_review_id')) {
            Schema::table('quran_listening_plans', function (Blueprint $table) {
                $table->dropConstrainedForeignId('khamsa_review_id');
            });
        }
    }

    private function deduplicatePlanItems(): void
    {
        DB::table('quran_listening_plan_items')
            ->whereNotIn('id', function ($query) {
                $query->selectRaw('min(id)')->from('quran_listening_plan_items')->groupBy('plan_id', 'juz');
            })
            ->delete();
    }

    private function hasPlanJuzUnique(): bool
    {
        return collect(Schema::getIndexes('quran_listening_plan_items'))
            ->contains(fn ($index) => $index['name'] === 'quran_listening_plan_items_plan_id_juz_unique');
    }
};
