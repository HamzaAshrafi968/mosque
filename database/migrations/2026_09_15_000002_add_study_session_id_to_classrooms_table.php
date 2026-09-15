<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreignUuid('study_session_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('study_sessions')
                ->nullOnDelete();
            $table->index(['tenant_id', 'study_session_id']);
        });

        // ربط الصفوف الحالية بدوام واحد فقط عندما تتفق كل شعبها على الدوام
        // نفسه، حتى لا تتغير بيانات الجامعات القائمة (الصفوف المشتركة تبقى
        // بدون دوام).
        DB::table('classrooms')->select('id')->orderBy('id')->each(function (object $classroom): void {
            $sessions = DB::table('sections')
                ->where('classroom_id', $classroom->id)
                ->pluck('study_session_id')
                ->unique();

            if ($sessions->count() === 1 && $sessions->first() !== null) {
                DB::table('classrooms')
                    ->where('id', $classroom->id)
                    ->update(['study_session_id' => $sessions->first()]);
            }
        });
    }

    public function down(): void
    {
        // SQLite cannot drop a column that is still part of an index,
        // so the composite index must be removed before the column.
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'study_session_id']);
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('study_session_id');
        });
    }
};
