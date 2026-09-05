<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
            $table->string('status')->default('active')->after('description');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
            $table->string('status')->default('active')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn(['description', 'status']);
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['description', 'status']);
        });
    }
};
