<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Business constants of the Quran programs module (spec §18).
 *
 * These are the "configured business rules" of the institution:
 * - monthly hafiz exams pass when the grade >= HAFIZ_EXAM_PASS_MARK
 * - qualifying completion requires at least QUALIFYING_MIN_PASSED_WEEKS
 * - ijazah completion requires at least IJAZAH_MIN_PASSED_MONTHS
 *
 * Adjust them here; every rule check reads these constants.
 */
final class QuranProgramSettings
{
    /** Pass mark (out of 100) for monthly hafiz exams. */
    public const HAFIZ_EXAM_PASS_MARK = 60;

    /** Minimum passing weekly evaluations before qualifying can be completed. */
    public const QUALIFYING_MIN_PASSED_WEEKS = 4;

    /** Minimum passing monthly evaluations before ijazah can be completed. */
    public const IJAZAH_MIN_PASSED_MONTHS = 1;

    /** Dashboard heuristic: total "new" pages beyond which a student is close to completing the Quran. */
    public const CLOSE_TO_COMPLETION_PAGES = 600;

    /**
     * Convert a date to the canonical YYYY-MM program month key.
     */
    public static function monthOf(\DateTimeInterface $date): string
    {
        return $date->format('Y-m');
    }

    /**
     * Previous month key (for paginating exam months).
     */
    public static function previousMonth(string $month): string
    {
        return Carbon::createFromFormat('Y-m', $month)->subMonth()->format('Y-m');
    }

    public static function nextMonth(string $month): string
    {
        return Carbon::createFromFormat('Y-m', $month)->addMonth()->format('Y-m');
    }

    public static function monthLabel(string $month): string
    {
        $names = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        [$year, $monthNumber] = explode('-', $month);

        return ($names[(int) $monthNumber - 1] ?? $monthNumber).' '.$year;
    }
}
