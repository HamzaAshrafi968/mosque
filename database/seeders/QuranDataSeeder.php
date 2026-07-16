<?php

namespace Database\Seeders;

use App\Models\QuranAyah;
use App\Models\QuranSurah;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class QuranDataSeeder extends Seeder
{
    public function run(): void
    {
        $surahs = [
            [
                'id' => (string) Str::uuid(),
                'name_arabic' => 'الفاتحة',
                'name_english' => 'Al-Fatiha',
                'revelation_type' => 'makkiah',
                'num_ayahs' => 7,
                'sort_order' => 1,
                'ayahs' => [
                    ['number' => 1, 'text' => 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ', 'text_simple' => 'بسم الله الرحمن الرحيم'],
                    ['number' => 2, 'text' => 'الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ', 'text_simple' => 'الحمد لله رب العالمين'],
                    ['number' => 3, 'text' => 'الرَّحْمَٰنِ الرَّحِيمِ', 'text_simple' => 'الرحمن الرحيم'],
                    ['number' => 4, 'text' => 'مَالِكِ يَوْمِ الدِّينِ', 'text_simple' => 'مالك يوم الدين'],
                    ['number' => 5, 'text' => 'إِيَّاكَ نَعْبُدُ وَإِيَّاكَ نَسْتَعِينُ', 'text_simple' => 'إياك نعبد وإياك نستعين'],
                    ['number' => 6, 'text' => 'اهْدِنَا الصِّرَاطَ الْمُسْتَقِيمَ', 'text_simple' => 'اهدنا الصراط المستقيم'],
                    ['number' => 7, 'text' => 'صِرَاطَ الَّذِينَ أَنْعَمْتَ عَلَيْهِمْ غَيْرِ الْمَغْضُوبِ عَلَيْهِمْ وَلَا الضَّالِّينَ', 'text_simple' => 'صراط الذين أنعمت عليهم غير المغضوب عليهم ولا الضالين'],
                ],
            ],
            [
                'id' => (string) Str::uuid(),
                'name_arabic' => 'الإخلاص',
                'name_english' => 'Al-Ikhlas',
                'revelation_type' => 'makkiah',
                'num_ayahs' => 4,
                'sort_order' => 112,
                'ayahs' => [
                    ['number' => 1, 'text' => 'قُلْ هُوَ اللَّهُ أَحَدٌ', 'text_simple' => 'قل هو الله أحد'],
                    ['number' => 2, 'text' => 'اللَّهُ الصَّمَدُ', 'text_simple' => 'الله الصمد'],
                    ['number' => 3, 'text' => 'لَمْ يَلِدْ وَلَمْ يُولَدْ', 'text_simple' => 'لم يلد ولم يولد'],
                    ['number' => 4, 'text' => 'وَلَمْ يَكُن لَّهُ كُفُوًا أَحَدٌ', 'text_simple' => 'ولم يكن له كفوا أحد'],
                ],
            ],
            [
                'id' => (string) Str::uuid(),
                'name_arabic' => 'الفلق',
                'name_english' => 'Al-Falaq',
                'revelation_type' => 'makkiah',
                'num_ayahs' => 5,
                'sort_order' => 113,
                'ayahs' => [
                    ['number' => 1, 'text' => 'قُلْ أَعُوذُ بِرَبِّ الْفَلَقِ', 'text_simple' => 'قل أعوذ برب الفلق'],
                    ['number' => 2, 'text' => 'مِن شَرِّ مَا خَلَقَ', 'text_simple' => 'من شر ما خلق'],
                    ['number' => 3, 'text' => 'وَمِن شَرِّ غَاسِقٍ إِذَا وَقَبَ', 'text_simple' => 'ومن شر غاسق إذا وقب'],
                    ['number' => 4, 'text' => 'وَمِن شَرِّ النَّفَّاثَاتِ فِي الْعُقَدِ', 'text_simple' => 'ومن شر النفاثات في العقد'],
                    ['number' => 5, 'text' => 'وَمِن شَرِّ حَاسِدٍ إِذَا حَسَدَ', 'text_simple' => 'ومن شر حاسد إذا حسد'],
                ],
            ],
            [
                'id' => (string) Str::uuid(),
                'name_arabic' => 'الناس',
                'name_english' => 'An-Nas',
                'revelation_type' => 'makkiah',
                'num_ayahs' => 6,
                'sort_order' => 114,
                'ayahs' => [
                    ['number' => 1, 'text' => 'قُلْ أَعُوذُ بِرَبِّ النَّاسِ', 'text_simple' => 'قل أعوذ برب الناس'],
                    ['number' => 2, 'text' => 'مَلِكِ النَّاسِ', 'text_simple' => 'ملك الناس'],
                    ['number' => 3, 'text' => 'إِلَٰهِ النَّاسِ', 'text_simple' => 'إله الناس'],
                    ['number' => 4, 'text' => 'مِن شَرِّ الْوَسْوَاسِ الْخَنَّاسِ', 'text_simple' => 'من شر الوسواس الخناس'],
                    ['number' => 5, 'text' => 'الَّذِي يُوَسْوِسُ فِي صُدُورِ النَّاسِ', 'text_simple' => 'الذي يوسوس في صدور الناس'],
                    ['number' => 6, 'text' => 'مِنَ الْجِنَّةِ وَالنَّاسِ', 'text_simple' => 'من الجنة والناس'],
                ],
            ],
            [
                'id' => (string) Str::uuid(),
                'name_arabic' => 'النصر',
                'name_english' => 'An-Nasr',
                'revelation_type' => 'madaniah',
                'num_ayahs' => 3,
                'sort_order' => 110,
                'ayahs' => [
                    ['number' => 1, 'text' => 'إِذَا جَاءَ نَصْرُ اللَّهِ وَالْفَتْحُ', 'text_simple' => 'إذا جاء نصر الله والفتح'],
                    ['number' => 2, 'text' => 'وَرَأَيْتَ النَّاسَ يَدْخُلُونَ فِي دِينِ اللَّهِ أَفْوَاجًا', 'text_simple' => 'ورأيت الناس يدخلون في دين الله أفواجا'],
                    ['number' => 3, 'text' => 'فَسَبِّحْ بِحَمْدِ رَبِّكَ وَاسْتَغْفِرْهُ ۚ إِنَّهُ كَانَ تَوَّابًا', 'text_simple' => 'فسبح بحمد ربك واستغفره إنه كان توابا'],
                ],
            ],
            [
                'id' => (string) Str::uuid(),
                'name_arabic' => 'المسد',
                'name_english' => 'Al-Masad',
                'revelation_type' => 'makkiah',
                'num_ayahs' => 5,
                'sort_order' => 111,
                'ayahs' => [
                    ['number' => 1, 'text' => 'تَبَّتْ يَدَا أَبِي لَهَبٍ وَتَبَّ', 'text_simple' => 'تبت يدا أبي لهب وتب'],
                    ['number' => 2, 'text' => 'مَا أَغْنَىٰ عَنْهُ مَالُهُ وَمَا كَسَبَ', 'text_simple' => 'ما أغنى عنه ماله وما كسب'],
                    ['number' => 3, 'text' => 'سَيَصْلَىٰ نَارًا ذَاتَ لَهَبٍ', 'text_simple' => 'سيصلى نارا ذات لهب'],
                    ['number' => 4, 'text' => 'وَامْرَأَتُهُ حَمَّالَةَ الْحَطَبِ', 'text_simple' => 'وامرأته حمالة الحطب'],
                    ['number' => 5, 'text' => 'فِي جِيدِهَا حَبْلٌ مِّن مَّسَدٍ', 'text_simple' => 'في جيدها حبل من مسد'],
                ],
            ],
        ];

        foreach ($surahs as $surahData) {
            $surah = QuranSurah::create([
                'id' => $surahData['id'],
                'name_arabic' => $surahData['name_arabic'],
                'name_english' => $surahData['name_english'],
                'revelation_type' => $surahData['revelation_type'],
                'num_ayahs' => $surahData['num_ayahs'],
                'sort_order' => $surahData['sort_order'],
            ]);

            foreach ($surahData['ayahs'] as $ayah) {
                QuranAyah::create([
                    'id' => (string) Str::uuid(),
                    'surah_id' => $surah->id,
                    'ayah_number' => $ayah['number'],
                    'text' => $ayah['text'],
                    'text_simple' => $ayah['text_simple'],
                ]);
            }
        }
    }
}
