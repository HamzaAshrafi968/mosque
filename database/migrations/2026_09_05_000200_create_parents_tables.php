<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parent/guardian accounts (spec §2): a parent (guardian) is a profile with an
 * optional login account (users) linked to one or more students through
 * `parent_students` with a relationship label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active'); // active|inactive
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('parent_students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('parent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->string('relationship')->default('guardian'); // father|mother|guardian|other
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'parent_id', 'student_id']);
            $table->index(['tenant_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_students');
        Schema::dropIfExists('parents');
    }
};
