<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_recitation_sessions', function (Blueprint $table) {
            $table->json('word_statuses')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('quran_recitation_sessions', function (Blueprint $table) {
            $table->dropColumn('word_statuses');
        });
    }
};
