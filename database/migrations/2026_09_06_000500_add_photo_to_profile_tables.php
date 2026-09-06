<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile photos (صورة شخصية) for every level: staff users (مدير/معلم accounts),
 * students, teachers and guardians. Files live on the public disk under
 * `avatars/` while the DB keeps the relative path.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'users' => 'phone',
            'teachers' => 'phone',
            'parents' => 'phone',
            'students' => 'gender',
        ];

        foreach ($tables as $table => $after) {
            Schema::table($table, function (Blueprint $table) use ($after) {
                $table->string('photo')->nullable()->after($after);
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'students', 'teachers', 'parents'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('photo');
            });
        }
    }
};
