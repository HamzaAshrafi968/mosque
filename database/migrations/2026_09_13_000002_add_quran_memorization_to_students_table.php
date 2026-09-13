<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->decimal('memorized_juz', 4, 1)->nullable()->after('notes');
            $table->foreignUuid('memorized_from_surah_id')->nullable()->after('memorized_juz')
                ->constrained('quran_surahs')->nullOnDelete();
            $table->unsignedInteger('memorized_from_ayah')->nullable()->after('memorized_from_surah_id');
            $table->foreignUuid('memorized_to_surah_id')->nullable()->after('memorized_from_ayah')
                ->constrained('quran_surahs')->nullOnDelete();
            $table->unsignedInteger('memorized_to_ayah')->nullable()->after('memorized_to_surah_id');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('memorized_from_surah_id');
            $table->dropConstrainedForeignId('memorized_to_surah_id');
            $table->dropColumn(['memorized_juz', 'memorized_from_ayah', 'memorized_to_ayah']);
        });
    }
};
