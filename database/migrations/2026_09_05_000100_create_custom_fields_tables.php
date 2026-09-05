<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type'); // student | teacher
            $table->string('name');
            $table->string('field_key');
            $table->string('field_type'); // text|textarea|number|date|boolean|select|multiselect
            $table->boolean('required')->default(false);
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'entity_type', 'field_key']);
            $table->index(['tenant_id', 'entity_type', 'is_active', 'sort_order']);
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('custom_field_id')->constrained()->cascadeOnDelete();
            $table->uuid('entity_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['custom_field_id', 'entity_id']);
            $table->index(['tenant_id', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
    }
};
