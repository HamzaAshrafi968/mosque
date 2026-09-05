<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional student portal login: one user account per student.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->after('section_id')->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
