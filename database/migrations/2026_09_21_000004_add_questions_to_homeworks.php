<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homeworks', function (Blueprint $table) {
            $table->decimal('total_marks', 6, 2)->nullable();
        });

        Schema::create('homework_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('homework_id')->constrained('homeworks')->cascadeOnDelete();
            $table->string('type'); // mcq | checkbox | true_false | short | essay
            $table->text('text');
            $table->decimal('marks', 6, 2)->default(1);
            $table->json('options')->nullable();
            $table->text('correct_answer')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['homework_id', 'sort_order']);
        });

        Schema::table('homework_submissions', function (Blueprint $table) {
            $table->json('answers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('homework_submissions', function (Blueprint $table) {
            $table->dropColumn('answers');
        });

        Schema::dropIfExists('homework_questions');

        Schema::table('homeworks', function (Blueprint $table) {
            $table->dropColumn('total_marks');
        });
    }
};
