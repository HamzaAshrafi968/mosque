<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_surahs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name_arabic');
            $table->string('name_english')->nullable();
            $table->enum('revelation_type', ['makkiah', 'madaniah']);
            $table->unsignedInteger('num_ayahs');
            $table->unsignedInteger('sort_order');
            $table->timestamps();
        });

        Schema::create('quran_ayahs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('surah_id')->constrained('quran_surahs')->cascadeOnDelete();
            $table->unsignedInteger('ayah_number');
            $table->text('text');
            $table->text('text_simple');
            $table->timestamps();

            $table->unique(['surah_id', 'ayah_number']);
            $table->index(['surah_id', 'ayah_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_ayahs');
        Schema::dropIfExists('quran_surahs');
    }
};
