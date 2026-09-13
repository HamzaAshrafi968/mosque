<?php

namespace App\Services;

use App\Models\QuranAyah;
use App\Models\QuranSurah;
use Illuminate\Support\Collection;

class QuranPageService
{
    public const MAX_PAGE = 604;

    public function maxPage(): int
    {
        return self::MAX_PAGE;
    }

    /**
     * All ayahs of a page, ordered by mushaf order (surah sort_order, then ayah_number).
     */
    public function ayahsForPage(int $page): Collection
    {
        return QuranAyah::query()
            ->with('surah:id,name_arabic,sort_order,num_ayahs')
            ->join('quran_surahs', 'quran_surahs.id', '=', 'quran_ayahs.surah_id')
            ->where('quran_ayahs.page', $page)
            ->orderBy('quran_surahs.sort_order')
            ->orderBy('quran_ayahs.ayah_number')
            ->select('quran_ayahs.*')
            ->get();
    }

    /**
     * A collection of pages with their ayahs and surah headers.
     */
    public function pagesForRange(int $from, int $to): Collection
    {
        $from = max(1, min($from, $to));
        $to = min(self::MAX_PAGE, max($from, $to));

        return collect(range($from, $to))->map(fn (int $page) => [
            'page' => $page,
            'ayahs' => $this->ayahsForPage($page),
            'surahStarts' => $this->surahStartsOnPage($page),
        ]);
    }

    /**
     * Surahs whose first ayah falls on the given page.
     */
    public function surahStartsOnPage(int $page): Collection
    {
        return QuranSurah::query()
            ->whereHas('ayahs', fn ($query) => $query->where('page', $page)->where('ayah_number', 1))
            ->orderBy('sort_order')
            ->get(['id', 'name_arabic', 'sort_order', 'num_ayahs']);
    }

    /**
     * The page containing the given surah/ayah, or null when pages are not seeded.
     */
    public function pageForAyah(int $surahNumber, int $ayahNumber): ?int
    {
        $surah = QuranSurah::query()->where('sort_order', $surahNumber)->first();

        if (! $surah) {
            return null;
        }

        return QuranAyah::query()
            ->where('surah_id', $surah->id)
            ->where('ayah_number', $ayahNumber)
            ->value('page');
    }

    public function pagesAreSeeded(): bool
    {
        return QuranAyah::query()->whereNotNull('page')->exists();
    }

    /**
     * JSON-ready payload for a single page (web json route + API).
     */
    public function pagePayload(int $page): array
    {
        $page = max(1, min($page, self::MAX_PAGE));
        $ayahs = $this->ayahsForPage($page);

        return [
            'page' => $page,
            'max_page' => self::MAX_PAGE,
            'juz' => (int) ($ayahs->first()->juz ?? 1),
            'ayahs' => $ayahs->map(fn ($ayah) => [
                'surah' => $ayah->surah->sort_order,
                'surah_name' => $ayah->surah->name_arabic,
                'ayah' => $ayah->ayah_number,
                'text' => $ayah->text,
                'juz' => $ayah->juz,
            ])->values()->all(),
            'surah_starts' => $this->surahStartsOnPage($page)->pluck('sort_order')->values()->all(),
        ];
    }
}
