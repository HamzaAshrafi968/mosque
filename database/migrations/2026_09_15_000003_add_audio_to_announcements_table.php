<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('audio_path')->nullable()->after('body');
            $table->string('audio_original_name')->nullable()->after('audio_path');
            $table->timestamp('expires_at')->nullable()->after('audio_original_name');
            $table->index('expires_at');
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('announcements')->whereNull('body')->update(['body' => '']);

        Schema::table('announcements', function (Blueprint $table) {
            $table->text('body')->nullable(false)->change();
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['audio_path', 'audio_original_name', 'expires_at']);
        });
    }
};
