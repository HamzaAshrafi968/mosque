<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sharia_course_students', function (Blueprint $table) {
            $table->string('guardian_name')->nullable()->after('birth_date');
        });
    }

    public function down(): void
    {
        Schema::table('sharia_course_students', function (Blueprint $table) {
            $table->dropColumn('guardian_name');
        });
    }
};
