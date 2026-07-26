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
        $path = database_path('data/quran.json');

        if (! file_exists($path)) {
            $this->command?->warn('quran.json not found. Run: php database/fetch-quran.php');

            return;
        }

        $surahs = json_decode(file_get_contents($path), true);

        $this->command?->info('Seeding '.count($surahs).' surahs...');

        foreach ($surahs as $surahData) {
            $surah = QuranSurah::create([
                'id' => (string) Str::uuid(),
                'name_arabic' => $surahData['name_arabic'],
                'name_english' => $surahData['name_english'],
                'revelation_type' => $surahData['revelation_type'],
                'num_ayahs' => $surahData['num_ayahs'],
                'sort_order' => $surahData['sort_order'],
            ]);

            $ayahs = [];
            foreach ($surahData['ayahs'] as $ayah) {
                $ayahs[] = [
                    'id' => (string) Str::uuid(),
                    'surah_id' => $surah->id,
                    'ayah_number' => $ayah['n'],
                    'text' => $ayah['t'],
                    'text_simple' => $ayah['ts'],
                ];
            }

            QuranAyah::insert($ayahs);
        }

        $this->command?->info('Quran data seeded successfully.');
    }
}
