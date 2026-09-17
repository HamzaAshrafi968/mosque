<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('exam_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // mcq | checkbox | true_false | short | essay
            $table->text('text');
            $table->decimal('marks', 6, 2)->default(1);
            $table->json('options')->nullable();
            $table->text('correct_answer')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['exam_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_questions');
    }
};
