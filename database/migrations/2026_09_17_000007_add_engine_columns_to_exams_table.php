<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدة محرّك الاختبارات الإلكترونية على جدول exams:
 * النوع (امتحان/مذاكرة)، المدة، وضع الأداء، حالة النشر، وملف PDF الاختياري.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('kind')->default('exam')->after('title');           // exam | quiz
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('exam_date');
            $table->string('mode')->default('onsite')->after('duration_minutes'); // onsite | online | hybrid
            $table->string('status')->default('draft')->after('mode');            // draft | published | closed
            $table->timestamp('published_at')->nullable()->after('status');
            $table->string('attachment_key')->nullable()->after('published_at');
            $table->string('attachment_name')->nullable()->after('attachment_key');

            $table->index(['tenant_id', 'status']);
        });

        // الامتحانات الموجودة مسبقاً كانت ظاهرة للطلاب مباشرة — تبقى منشورة
        // حتى لا تختفي بعد إضافة حالة النشر.
        DB::table('exams')->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn([
                'kind',
                'duration_minutes',
                'mode',
                'status',
                'published_at',
                'attachment_key',
                'attachment_name',
            ]);
        });
    }
};
