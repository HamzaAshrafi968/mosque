<?php

use App\Models\QuranAyah;
use App\Support\QuranJuzMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تصحيح quran_ayahs.juz من خريطة الأجزاء القياسية: المولّد القديم
 * (fetch-quran-pages.php) كان يتجاهل الأجزاء التي تبدأ وسط صفحة (4 و7 و11 و26)
 * فتمتد بيانات الجزء السابق إليها خطأً.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! QuranAyah::query()->whereNotNull('page')->exists()) {
            return;
        }

        foreach (range(1, QuranJuzMap::TOTAL_JUZ) as $juz) {
            $range = QuranJuzMap::pageRange($juz);

            DB::table('quran_ayahs')
                ->whereBetween('page', [$range['from'], $range['to']])
                ->where(function ($query) use ($juz) {
                    $query->where('juz', '!=', $juz)->orWhereNull('juz');
                })
                ->update(['juz' => $juz]);
        }
    }

    public function down(): void
    {
        // البيانات المصححة لا تُعاد إلى القيم الخاطئة.
    }
};
