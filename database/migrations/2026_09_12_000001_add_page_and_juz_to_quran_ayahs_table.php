<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_ayahs', function (Blueprint $table) {
            $table->unsignedSmallInteger('page')->nullable()->after('text_simple');
            $table->unsignedTinyInteger('juz')->nullable()->after('page');

            $table->index('page');
            $table->index('juz');
        });
    }

    public function down(): void
    {
        Schema::table('quran_ayahs', function (Blueprint $table) {
            $table->dropIndex(['page']);
            $table->dropIndex(['juz']);
            $table->dropColumn(['page', 'juz']);
        });
    }
};
