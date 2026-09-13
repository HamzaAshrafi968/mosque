<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط البرامج/التخصصات بالدوامات: لكل دوام برامجه المتاحة، وإن لم يُحدد
 * له شيء تظهر كل البرامج المفعّلة (سلوك متوافق مع البيانات القديمة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_study_session', function (Blueprint $table) {
            $table->foreignUuid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignUuid('study_session_id')->constrained('study_sessions')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['program_id', 'study_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_study_session');
    }
};
