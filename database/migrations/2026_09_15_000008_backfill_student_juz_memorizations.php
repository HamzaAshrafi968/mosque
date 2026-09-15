<?php

use App\Support\QuranJuzMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * تعبئة الأجزاء المحفوظة للطلاب القدامى من مقدار الحفظ في ملفهم
 * (`students.memorized_juz` → الأجزاء 1..floor(N)) حتى تُفتح خمساتهم في
 * «مراجعة 5» مباشرة. الطلاب الذين أضافوا أجزاءً يدوياً لاحقاً لا يتأثرون
 * (قيود unique على student_id + juz).
 */
return new class extends Migration
{
    public function up(): void
    {
        $students = DB::table('students')
            ->whereNotNull('memorized_juz')
            ->where('memorized_juz', '>', 0)
            ->get(['id', 'tenant_id', 'memorized_juz']);

        if ($students->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($students as $student) {
            $count = max(0, min(QuranJuzMap::TOTAL_JUZ, (int) floor((float) $student->memorized_juz)));

            for ($juz = 1; $juz <= $count; $juz++) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $student->tenant_id,
                    'student_id' => $student->id,
                    'juz' => $juz,
                    'memorized_at' => $now->toDateString(),
                    'recorded_by' => null,
                    'source' => 'intake',
                    'notes' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('student_juz_memorizations')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        // لا تُحذف الأجزاء المسجّلة عند التراجع.
    }
};
