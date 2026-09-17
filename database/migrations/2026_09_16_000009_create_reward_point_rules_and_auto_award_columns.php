<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_point_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('study_session_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type', 30);
            $table->unsignedInteger('pages_count')->nullable();
            $table->unsignedInteger('points')->nullable();
            $table->timestamps();

            $table->unique(['study_session_id', 'rule_type']);
        });

        Schema::table('reward_points', function (Blueprint $table) {
            $table->foreignUuid('study_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 60)->nullable();
            $table->uuid('source_id')->nullable();
            $table->unsignedInteger('source_pages')->nullable();

            $table->index(['source_type', 'source_id']);
        });

        Schema::table('reward_points', function (Blueprint $table) {
            $table->foreignUuid('awarded_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reward_points', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropConstrainedForeignId('study_session_id');
            $table->dropColumn(['source_type', 'source_id', 'source_pages']);
        });

        Schema::table('reward_points', function (Blueprint $table) {
            $table->foreignUuid('awarded_by')->nullable(false)->change();
        });

        Schema::dropIfExists('reward_point_rules');
    }
};
