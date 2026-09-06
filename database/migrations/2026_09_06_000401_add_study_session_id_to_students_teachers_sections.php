<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['students', 'teachers', 'sections'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignUuid('study_session_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('study_sessions')
                    ->nullOnDelete();
                $table->index(['tenant_id', 'study_session_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['sections', 'teachers', 'students'] as $table) {
            // SQLite cannot drop a column that is still part of an index,
            // so the composite index must be removed before the column.
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'study_session_id']);
            });

            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('study_session_id');
            });
        }
    }
};
