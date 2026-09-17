<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sharia_courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
        });
    }

    public function down(): void
    {
        Schema::table('sharia_courses', function (Blueprint $table) {
            $table->foreignUuid('supervisor_id')->nullable()->after('description')->constrained('teachers')->nullOnDelete();
        });
    }
};
