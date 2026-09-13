<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تخصصات الجداول (programs): برنامج التحفيظ، برنامج الإجازة، اختبارات الحفظ،
 * الدورات الشرعية، البرامج القرآنية، وأي برنامج يضيفه المدير بخصائصه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('type')->default('custom'); // tahfeez|ijazah|hafiz_exams|sharia_courses|quran|custom
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });

        Schema::create('program_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['program_id', 'name']);
            $table->index(['tenant_id', 'program_id', 'sort_order']);
        });

        Schema::create('program_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('field_key');
            $table->string('field_type'); // text|textarea|number|date|boolean|select|multiselect
            $table->boolean('required')->default(false);
            $table->json('options')->nullable();
            $table->text('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'program_id', 'field_key']);
            $table->index(['tenant_id', 'program_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_attributes');
        Schema::dropIfExists('program_periods');
        Schema::dropIfExists('programs');
    }
};
