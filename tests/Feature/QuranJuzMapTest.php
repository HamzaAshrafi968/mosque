<?php

namespace Tests\Feature;

use App\Support\QuranJuzMap;
use Tests\TestCase;

class QuranJuzMapTest extends TestCase
{
    public function test_juz_page_ranges_match_the_madani_mushaf(): void
    {
        $this->assertSame(['from' => 1, 'to' => 21], QuranJuzMap::pageRange(1));
        $this->assertSame(['from' => 62, 'to' => 81], QuranJuzMap::pageRange(4));
        $this->assertSame(['from' => 121, 'to' => 141], QuranJuzMap::pageRange(7));
        $this->assertSame(['from' => 582, 'to' => 604], QuranJuzMap::pageRange(30));
    }

    public function test_khamsat_are_five_pages_and_the_last_absorbs_the_remainder(): void
    {
        $juz1 = QuranJuzMap::khamsat(1);
        $this->assertSame(['from' => 1, 'to' => 5], $juz1[1]);
        $this->assertSame(['from' => 6, 'to' => 10], $juz1[2]);
        $this->assertSame(['from' => 11, 'to' => 15], $juz1[3]);
        $this->assertSame(['from' => 16, 'to' => 21], $juz1[4]);

        $juz30 = QuranJuzMap::khamsat(30);
        $this->assertSame(['from' => 582, 'to' => 586], $juz30[1]);
        $this->assertSame(['from' => 597, 'to' => 604], $juz30[4]);

        $juz6 = QuranJuzMap::khamsat(6);
        $this->assertSame(['from' => 117, 'to' => 120], $juz6[4]);
    }

    public function test_juz_for_page_boundaries(): void
    {
        $this->assertSame(1, QuranJuzMap::juzForPage(21));
        $this->assertSame(2, QuranJuzMap::juzForPage(22));
        $this->assertSame(4, QuranJuzMap::juzForPage(62));
        $this->assertSame(30, QuranJuzMap::juzForPage(604));
    }

    public function test_khamsat_cover_every_juz_completely(): void
    {
        foreach (range(1, QuranJuzMap::TOTAL_JUZ) as $juz) {
            $range = QuranJuzMap::pageRange($juz);
            $khamsat = QuranJuzMap::khamsat($juz);

            $this->assertCount(4, $khamsat);
            $this->assertSame($range['from'], $khamsat[1]['from']);
            $this->assertSame($range['to'], $khamsat[4]['to']);

            $covered = 0;

            foreach ($khamsat as $khamsa) {
                $this->assertGreaterThanOrEqual($range['from'], $khamsa['from']);
                $this->assertLessThanOrEqual($range['to'], $khamsa['to']);
                $covered += $khamsa['to'] - $khamsa['from'] + 1;
            }

            $this->assertSame($range['to'] - $range['from'] + 1, $covered);
        }
    }
}
