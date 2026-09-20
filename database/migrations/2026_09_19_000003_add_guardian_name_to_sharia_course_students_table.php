<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sharia_course_students', 'guardian_name')) {
            return;
        }

        Schema::table('sharia_course_students', function (Blueprint $table) {
            $table->string('guardian_name')->nullable()->after('birth_date');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sharia_course_students', 'guardian_name')) {
            return;
        }

        Schema::table('sharia_course_students', function (Blueprint $table) {
            $table->dropColumn('guardian_name');
        });
    }
};
