<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_points', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('awarded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('quran_review_session_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('points');
            $table->string('reason')->nullable();
            $table->string('type')->default('earned');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
            $table->index('awarded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_points');
    }
};
