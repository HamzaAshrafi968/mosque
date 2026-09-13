<?php

namespace Database\Seeders;

use App\Models\QuranAyah;
use App\Models\QuranSurah;
use Illuminate\Database\Seeder;

class QuranPageSeeder extends Seeder
{
    /**
     * Fills quran_ayahs.page and quran_ayahs.juz from the Madani layout file.
     */
    public function run(): void
    {
        $path = database_path('data/quran_pages.json');

        if (! file_exists($path)) {
            $this->command?->warn('quran_pages.json not found. Run: php database/fetch-quran-pages.php');

            return;
        }

        $pages = json_decode(file_get_contents($path), true);

        if (! is_array($pages) || $pages === []) {
            $this->command?->warn('quran_pages.json is empty or invalid.');

            return;
        }

        $surahs = QuranSurah::query()->get(['id', 'sort_order', 'num_ayahs'])->keyBy('sort_order');

        foreach ($pages as $page) {
            $pageNumber = (int) $page['page'];
            $juz = isset($page['juz']) ? (int) $page['juz'] : null;

            [$startSurah, $startAyah] = array_map('intval', explode(':', (string) $page['start']));
            [$endSurah, $endAyah] = array_map('intval', explode(':', (string) $page['end']));

            for ($surahNumber = $startSurah; $surahNumber <= $endSurah; $surahNumber++) {
                $surah = $surahs->get($surahNumber);

                if (! $surah) {
                    continue;
                }

                $from = $surahNumber === $startSurah ? $startAyah : 1;
                $to = $surahNumber === $endSurah ? $endAyah : (int) $surah->num_ayahs;

                QuranAyah::query()
                    ->where('surah_id', $surah->id)
                    ->whereBetween('ayah_number', [$from, $to])
                    ->update(['page' => $pageNumber, 'juz' => $juz]);
            }
        }

        $missing = QuranAyah::query()->whereNull('page')->count();

        $this->command?->info('Quran pages seeded: '.count($pages).' pages, '.$missing.' ayahs without page.');

        if ($missing > 0) {
            $this->command?->warn('Some ayahs were not mapped to a page. Verify quran.json and quran_pages.json are in sync.');
        }
    }
}
