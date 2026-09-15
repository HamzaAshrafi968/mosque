<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * خريطة الأجزاء الثلاثين في المصحف المدني (٦٠٤ صفحات):
 *
 * - بداية كل جزء (سورة/آية) وصفحته.
 * - تقسيم كل جزء إلى أربع «خمسات»: كل خمسة ٥ صفحات، والخمسة الأخيرة تأخذ
 *   الباقي (الجزء ١ = ٢١ صفحة، الجزء ٣٠ = ٢٣ صفحة، الجزء ٦ = ١٩ صفحة).
 *
 * تُستخدم في ميزة «مراجعة 5» وفي تصحيح بيانات quran_ayahs.juz.
 */
final class QuranJuzMap
{
    public const TOTAL_PAGES = 604;

    public const TOTAL_JUZ = 30;

    public const KHAMSA_PAGES = 5;

    public const KHAMSAT_PER_JUZ = 4;

    /** [الجزء => صفحة البداية] في مصحف المدينة. */
    public const START_PAGES = [
        1 => 1,
        2 => 22,
        3 => 42,
        4 => 62,
        5 => 82,
        6 => 102,
        7 => 121,
        8 => 142,
        9 => 162,
        10 => 182,
        11 => 201,
        12 => 222,
        13 => 242,
        14 => 262,
        15 => 282,
        16 => 302,
        17 => 322,
        18 => 342,
        19 => 362,
        20 => 382,
        21 => 402,
        22 => 422,
        23 => 442,
        24 => 462,
        25 => 482,
        26 => 502,
        27 => 522,
        28 => 542,
        29 => 562,
        30 => 582,
    ];

    /** [الجزء => [ترتيب السورة، رقم الآية]] أول آية في الجزء. */
    public const START_POSITIONS = [
        1 => [1, 1],
        2 => [2, 142],
        3 => [2, 253],
        4 => [3, 92],
        5 => [4, 24],
        6 => [4, 148],
        7 => [5, 82],
        8 => [6, 111],
        9 => [7, 88],
        10 => [8, 41],
        11 => [9, 93],
        12 => [11, 6],
        13 => [12, 53],
        14 => [15, 1],
        15 => [17, 1],
        16 => [18, 75],
        17 => [21, 1],
        18 => [23, 1],
        19 => [25, 21],
        20 => [27, 56],
        21 => [29, 46],
        22 => [33, 31],
        23 => [36, 28],
        24 => [39, 32],
        25 => [41, 47],
        26 => [46, 1],
        27 => [51, 31],
        28 => [58, 1],
        29 => [67, 1],
        30 => [78, 1],
    ];

    public static function assertJuz(int $juz): void
    {
        if ($juz < 1 || $juz > self::TOTAL_JUZ) {
            throw new InvalidArgumentException("رقم الجزء غير صحيح: {$juz}");
        }
    }

    /** آخر صفحة في الجزء. */
    public static function endPage(int $juz): int
    {
        self::assertJuz($juz);

        return $juz >= self::TOTAL_JUZ
            ? self::TOTAL_PAGES
            : self::START_PAGES[$juz + 1] - 1;
    }

    /** @return array{from: int, to: int} نطاق صفحات الجزء كاملاً. */
    public static function pageRange(int $juz): array
    {
        self::assertJuz($juz);

        return ['from' => self::START_PAGES[$juz], 'to' => self::endPage($juz)];
    }

    /**
     * خمسات الجزء الأربع، مرتّبة: الخمسة الرابعة تمتد حتى نهاية الجزء.
     *
     * @return array<int, array{from: int, to: int}>
     */
    public static function khamsat(int $juz): array
    {
        $range = self::pageRange($juz);
        $khamsat = [];

        for ($khamsa = 1; $khamsa <= self::KHAMSAT_PER_JUZ; $khamsa++) {
            $from = $range['from'] + ($khamsa - 1) * self::KHAMSA_PAGES;

            if ($from > $range['to']) {
                break;
            }

            $to = $khamsa === self::KHAMSAT_PER_JUZ
                ? $range['to']
                : min($from + self::KHAMSA_PAGES - 1, $range['to']);

            $khamsat[$khamsa] = ['from' => $from, 'to' => $to];
        }

        return $khamsat;
    }

    /** @return array{from: int, to: int} */
    public static function khamsaRange(int $juz, int $khamsa): array
    {
        self::assertJuz($juz);

        if ($khamsa < 1 || $khamsa > self::KHAMSAT_PER_JUZ) {
            throw new InvalidArgumentException("رقم الخمسة غير صحيح: {$khamsa}");
        }

        $khamsat = self::khamsat($juz);

        if (! isset($khamsat[$khamsa])) {
            throw new InvalidArgumentException("الخمسة {$khamsa} غير موجودة في الجزء {$juz}");
        }

        return $khamsat[$khamsa];
    }

    /** الجزء الذي تنتمي إليه صفحة مصحف (1-604). */
    public static function juzForPage(int $page): int
    {
        if ($page < 1 || $page > self::TOTAL_PAGES) {
            throw new InvalidArgumentException("رقم الصفحة غير صحيح: {$page}");
        }

        for ($juz = self::TOTAL_JUZ; $juz >= 1; $juz--) {
            if ($page >= self::START_PAGES[$juz]) {
                return $juz;
            }
        }

        return 1;
    }

    /** وصف مختصر: «الجزء ١ — الخمسة ٢ (صفحات ٦–١٠)». */
    public static function khamsaLabel(int $juz, int $khamsa): string
    {
        $range = self::khamsaRange($juz, $khamsa);

        return "الجزء {$juz} — الخمسة {$khamsa} (صفحات {$range['from']}–{$range['to']})";
    }
}
