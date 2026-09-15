<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الأجزاء المحفوظة لكل طالب: تُفتح خمسات الجزء في «مراجعة 5» فقط إذا كان
 * الجزء مسجّلاً هنا (يدوياً أو من مقدار الحفظ أو من تسميع «جديد»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_juz_memorizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedTinyInteger('juz');
            $table->date('memorized_at')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'juz']);
            $table->index(['tenant_id', 'juz']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_juz_memorizations');
    }
};
