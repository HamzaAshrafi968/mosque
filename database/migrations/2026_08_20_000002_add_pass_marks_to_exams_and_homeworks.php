<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedSmallInteger('pass_marks')->default(50)->after('total_marks');
        });

        Schema::table('homeworks', function (Blueprint $table) {
            $table->unsignedSmallInteger('pass_marks')->default(50)->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('pass_marks');
        });

        Schema::table('homeworks', function (Blueprint $table) {
            $table->dropColumn('pass_marks');
        });
    }
};
