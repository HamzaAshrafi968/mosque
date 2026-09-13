<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quran_review_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('from_page')->nullable()->after('to_ayah');
            $table->unsignedSmallInteger('to_page')->nullable()->after('from_page');
        });
    }

    public function down(): void
    {
        Schema::table('quran_review_sessions', function (Blueprint $table) {
            $table->dropColumn(['from_page', 'to_page']);
        });
    }
};
