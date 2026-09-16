<?php

namespace App\Services;

use App\Models\QuranListeningPlanItem;

/**
 * تلاوات «خطة الاستماع»: بناء قائمة تشغيل لكل آية في نطاق صفحات من مصدر
 * خارجي (CDN) دون تخزين محلي. الصيغة: SSSAAA.mp3 لكل آية.
 */
class QuranAudioService
{
    public function __construct(private readonly QuranPageService $pages) {}

    /** @return array<string, string> كود القارئ => الاسم المعروض */
    public function reciters(): array
    {
        return collect(config('quran_audio.reciters', []))
            ->map(fn (array $reciter) => (string) ($reciter['label'] ?? ''))
            ->all();
    }

    public function defaultReciter(): string
    {
        $reciters = config('quran_audio.reciters', []);
        $reciter = (string) config('quran_audio.reciter', 'ar.alafasy');

        if (array_key_exists($reciter, $reciters)) {
            return $reciter;
        }

        return (string) (array_key_first($reciters) ?? $reciter);
    }

    public function baseUrl(?string $reciter = null): string
    {
        return rtrim((string) ($this->reciterConfig($reciter)['base_url'] ?? ''), '/');
    }

    /** رابط آية واحدة: SSSAAA.mp3 (مثال: 001001.mp3). */
    public function trackUrl(int $surah, int $ayah, ?string $reciter = null): string
    {
        return $this->baseUrl($reciter).'/'.sprintf('%03d%03d.mp3', $surah, $ayah);
    }

    /**
     * قائمة تشغيل نطاق صفحات: مقطع لكل آية بترتيب المصحف.
     *
     * @return array<int, array{page: int, surah: int, surah_name: string, ayah: int, url: string, text: string}>
     */
    public function playlistFor(int $fromPage, int $toPage, ?string $reciter = null): array
    {
        return $this->pages->ayahsForRange($fromPage, $toPage)
            ->map(fn ($ayah) => [
                'page' => (int) $ayah->page,
                'surah' => (int) $ayah->surah->sort_order,
                'surah_name' => (string) $ayah->surah->name_arabic,
                'ayah' => (int) $ayah->ayah_number,
                'url' => $this->trackUrl((int) $ayah->surah->sort_order, (int) $ayah->ayah_number, $reciter),
                'text' => (string) $ayah->text,
            ])
            ->values()
            ->all();
    }

    /**
     * حمولة قائمة التشغيل لعنصر خطة استماع (تُرسل للواجهة JSON).
     *
     * @return array{reciter: string, reciter_label: string, juz: int, from_page: int, to_page: int, pages: int, tracks: array<int, array<string, mixed>>}
     */
    public function playlistForItem(QuranListeningPlanItem $item, ?string $reciter = null): array
    {
        $code = $reciter ?? $this->defaultReciter();
        $config = $this->reciterConfig($code);

        return [
            'reciter' => $code,
            'reciter_label' => (string) ($config['label'] ?? ''),
            'juz' => (int) $item->juz,
            'from_page' => (int) $item->from_page,
            'to_page' => (int) $item->to_page,
            'pages' => $item->pagesCount(),
            'tracks' => $this->playlistFor((int) $item->from_page, (int) $item->to_page, $code),
        ];
    }

    /**
     * إعدادات القارئ من المصفوفة مباشرة (أكواد القرّاء تحتوي نقاطاً فلا
     * تصلح معها مسارات config النقطية).
     *
     * @return array{label?: string, base_url?: string}
     */
    private function reciterConfig(?string $reciter = null): array
    {
        $reciters = config('quran_audio.reciters', []);
        $code = $reciter ?? $this->defaultReciter();
        $config = $reciters[$code] ?? null;

        return is_array($config) ? $config : [];
    }
}
