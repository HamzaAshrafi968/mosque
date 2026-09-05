<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student homework submission body (portal submission support, spec §18).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_submissions', function (Blueprint $table) {
            $table->longText('content')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('homework_submissions', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
