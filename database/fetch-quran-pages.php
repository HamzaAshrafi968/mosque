<?php

/**
 * Generates database/data/quran_pages.json — the Madani mushaf page layout (604 pages).
 *
 * Source: https://api.alquran.cloud/v1/meta (King Fahd Complex Madani layout).
 * Each page entry: { "page": 1, "start": "1:1", "end": "1:7", "juz": 1 }
 * where start/end use the "surah:ayah" format (mushaf order) and may span two surahs.
 *
 * Run manually: php database/fetch-quran-pages.php
 */
$url = 'https://api.alquran.cloud/v1/meta';

$json = @file_get_contents($url);
if ($json === false) {
    fwrite(STDERR, "تعذّر جلب بيانات التخطيط من {$url}\n");
    exit(1);
}

$meta = json_decode($json, true);
if (! isset($meta['data']['pages']['references'], $meta['data']['surahs']['references'], $meta['data']['juzs']['references'])) {
    fwrite(STDERR, "استجابة غير متوقعة من المصدر\n");
    exit(1);
}

$pageRefs = $meta['data']['pages']['references'];
$surahAyahs = [];
foreach ($meta['data']['surahs']['references'] as $surah) {
    $surahAyahs[(int) $surah['number']] = (int) $surah['numberOfAyahs'];
}

$pageCount = count($pageRefs);
$juzRefs = $meta['data']['juzs']['references'];

$juzStartPages = [];
foreach ($juzRefs as $index => $ref) {
    $juzStartPages[$index + 1] = null;
    foreach ($pageRefs as $pageIndex => $pageRef) {
        if ((int) $pageRef['surah'] === (int) $ref['surah'] && (int) $pageRef['ayah'] === (int) $ref['ayah']) {
            $juzStartPages[$index + 1] = $pageIndex + 1;
            break;
        }
    }
}

$pages = [];
for ($i = 0; $i < $pageCount; $i++) {
    $start = $pageRefs[$i];

    if ($i + 1 < $pageCount) {
        $next = $pageRefs[$i + 1];
        if ((int) $next['ayah'] > 1) {
            $end = ['surah' => (int) $next['surah'], 'ayah' => (int) $next['ayah'] - 1];
        } else {
            $previousSurah = (int) $next['surah'] - 1;
            $end = ['surah' => $previousSurah, 'ayah' => $surahAyahs[$previousSurah]];
        }
    } else {
        $end = ['surah' => 114, 'ayah' => $surahAyahs[114]];
    }

    $juz = 1;
    foreach ($juzStartPages as $juzNumber => $startPage) {
        if ($startPage !== null && $startPage <= $i + 1) {
            $juz = $juzNumber;
        }
    }

    $pages[] = [
        'page' => $i + 1,
        'start' => $start['surah'].':'.$start['ayah'],
        'end' => $end['surah'].':'.$end['ayah'],
        'juz' => $juz,
    ];
}

$output = __DIR__.'/data/quran_pages.json';
$written = file_put_contents($output, json_encode($pages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

if ($written === false) {
    fwrite(STDERR, "تعذّر كتابة الملف {$output}\n");
    exit(1);
}

echo 'تم توليد '.count($pages)." صفحة في {$output}\n";
